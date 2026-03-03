<?php

namespace Autarco\JiraKpi\Infrastructure\Database\Mapper;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use LogicException;
use Marble\Entity\Entity;
use Marble\Entity\Identifier;
use Marble\Entity\SimpleId;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\Contract\EntityWriter;
use Marble\EntityManager\Read\Criteria;
use Marble\EntityManager\Read\DataCollector;
use Marble\EntityManager\Read\ReadContext;
use Marble\EntityManager\Write\DeleteContext;
use Marble\EntityManager\Write\HasChanged;
use Marble\EntityManager\Write\Persistable;
use Marble\EntityManager\Write\WriteContext;
use Autarco\JiraKpi\Domain\Model\Unit\Unit;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Uid\AbstractUid;
use UnitEnum;
use function Symfony\Component\String\u;

/**
 * @template T of Entity
 * @implements EntityReader<T>
 * @implements EntityWriter<T>
 */
abstract class BaseEntityMapper implements EntityReader, EntityWriter
{
    private array $reflectionProperties = [];

    /**
     * @var array<class-string, callable>
     */
    private array $queryHandlerMethods = [null];

    public function __construct(
        protected readonly Connection      $connection,
        protected readonly LoggerInterface $logger,
    ) {
    }

    abstract public static function getEntityClassName(): string;

    protected function tableName(): string
    {
        return u($this->getEntityClassName())->afterLast('\\')->snake();
    }

    protected function idField(): string
    {
        return 'id';
    }

    final protected function escape(string $field, bool $ensureSnake = true): string
    {
        if ($ensureSnake) {
            $field = u($field)->snake();
        }

        return "`$field`";
    }

    // READING

    public function read(?object $query, DataCollector $dataCollector, ReadContext $context): void
    {
        $sqlBuilder = $this->createSqlBuilder();

        if ($query !== null) {
            $this->where($sqlBuilder, $query);
        }

        $this->logger->info($sqlBuilder->getSQL());

        $result = $sqlBuilder->executeQuery();

        while (($row = $result->fetchAssociative()) !== false) {
            $this->collectDataFromRow($row, $context, $dataCollector);
        }
    }

    protected function createSqlBuilder(): QueryBuilder
    {
        $tableName  = $this->tableName();
        $tableAlias = substr($tableName, 0, 1);

        return $this->connection->createQueryBuilder()->select($tableAlias . '.*')->from($tableName, $tableAlias);
    }

    protected function where(QueryBuilder $sqlBuilder, object $query): void
    {
        if ($query instanceof Identifier) {
            $sqlBuilder->andWhere($this->escape($this->idField()) . ' = ' . $this->toSqlParam($sqlBuilder, $query));
        } elseif ($query instanceof Criteria) {
            $this->whereCriteria($sqlBuilder, $query);
        } else {
            $this->detectQueryHandlerMethods();

            foreach ($this->queryHandlerMethods as $className => $method) {
                if ($query instanceof $className) {
                    $method($sqlBuilder, $query);

                    return;
                }
            }

            throw new LogicException(sprintf("Query object %s not supported by %s.", $query::class, $this::class));
        }
    }

    private function detectQueryHandlerMethods(): void
    {
        if (!(count($this->queryHandlerMethods) === 1 && $this->queryHandlerMethods[0] === null)) {
            return; // already parsed class
        }

        $this->queryHandlerMethods = [];
        $reflectionMethods         = (new ReflectionClass($this))->getMethods();

        foreach ($reflectionMethods as $reflectionMethod) {
            $methodName = $reflectionMethod->name;

            if (str_starts_with($methodName, 'where') && $reflectionMethod->class !== self::class) {
                $parameters = $reflectionMethod->getParameters();

                if ($parameters[0]->getType()->getName() === QueryBuilder::class) {
                    $queryClass = $parameters[1]->getType()->getName();

                    $this->queryHandlerMethods[$queryClass] = $this->$methodName(...);
                }
            }
        }
    }

    protected function whereCriteria(QueryBuilder $sqlBuilder, Criteria $criteria): void
    {
        foreach ($criteria as $field => $value) {
            $sqlBuilder->andWhere($this->escape($field) . ' = ' . $this->toSqlParam($sqlBuilder, $value));
        }

        if ($field = $criteria->getSortBy()) {
            $sqlBuilder->orderBy($this->escape($field), $criteria->getSortDirection()->name);
        }
    }

    protected function toSqlValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Entity          => $value->getId(),
            $value instanceof Identifier      => $value instanceof AbstractUid ? $value->toBase58() : (string) $value,
            $value instanceof UnitEnum        => $value->name,
            $value instanceof CarbonInterface => $value->copy()->setTimezone('UTC')->toDateTimeString(),
            $value instanceof Unit            => $value->value,
            default                           => $value,
        };
    }

    protected function toSqlParam(QueryBuilder $sqlBuilder, mixed $value): string
    {
        $value = $this->toSqlValue($value);

        return $sqlBuilder->createNamedParameter($value);
    }

    protected function collectDataFromRow(array $row, ReadContext $context, DataCollector $dataCollector): void
    {
        $idValue = new SimpleId($row[(string) u($this->idField())->snake()]);
        $data    = $this->prepareDataForHydration($row, $context);

        $data[$this->idField()] = $idValue;

        $dataCollector->put($idValue, $data);
    }

    protected function prepareDataForHydration(array $row, ReadContext $context): array
    {
        $data = [];

        foreach ($row as $column => $value) {
            $key                    = (string) u($column)->camel();
            $reflectionProperty     = $this->reflectionProperties[$key] ??= new ReflectionProperty($this->getEntityClassName(), $key);
            $reflectionPropertyType = $reflectionProperty->getType();

            if ($value !== null && $reflectionPropertyType instanceof ReflectionNamedType) {
                $fieldType = $reflectionPropertyType->getName();

                if (enum_exists($fieldType)) {
                    $value = $fieldType::{$value};
                } elseif (is_a($fieldType, CarbonImmutable::class, true)) {
                    $value = CarbonImmutable::make($value);
                } elseif (is_a($fieldType, Entity::class, true)) {
                    $value = $context->getRepository($fieldType)->fetchOne(new SimpleId($value));
                } elseif (is_a($fieldType, Unit::class, true)) {
                    $value = new $fieldType($value);
                } elseif (is_a($fieldType, SimpleId::class, true)) {
                    $value = new SimpleId($value);
                }
            }

            $data[$key] = $value;
        }

        return $data;
    }

    // WRITING

    public function write(Persistable $persistable, WriteContext $context): void
    {
        $tableName = $this->tableName();
        $idField   = $this->escape($this->idField());
        $idValue   = $this->toSqlValue($persistable->getEntity());
        $data      = $this->prepareDataForPersistence($persistable);

        if ($persistable instanceof HasChanged) {
            $this->connection->update($tableName, $data, [
                $idField => $idValue,
            ]);
        } else {
            $this->connection->insert($tableName, [
                $idField => $idValue,
                ...$data,
            ]);
        }
    }

    protected function prepareDataForPersistence(Persistable $persistable): array
    {
        $result = [];

        foreach ($persistable->getData() as $key => $value) {
            if ($key === (string) u($this->idField())->camel()) {
                continue; // skip id field
            }

            $result[$this->escape($key)] = $this->toSqlValue($value);
        }

        return $result;
    }

    public function delete(Entity $entity, DeleteContext $context): void
    {
        $this->connection->delete($this->tableName(), [
            $this->escape($this->idField()) => $this->toSqlValue($entity),
        ]);
    }
}
