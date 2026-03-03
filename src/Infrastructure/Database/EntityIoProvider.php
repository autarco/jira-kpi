<?php

namespace Autarco\JiraKpi\Infrastructure\Database;

use Marble\EntityManager\Contract\EntityIoProvider as EntityIoProviderInterface;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\Contract\EntityWriter;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class EntityIoProvider implements EntityIoProviderInterface, ServiceSubscriberInterface
{
    public function __construct(private readonly ContainerInterface $locator)
    {
    }

    public static function getSubscribedServices(): array
    {
        $finder = new Finder();
        $result = [];

        $finder->in(__DIR__ . '/Mapper')->files()->name('*Mapper.php');

        foreach ($finder as $file) {
            $fqn = __NAMESPACE__ . '\\Mapper\\' . $file->getFilenameWithoutExtension();

            if (class_exists($fqn)) {
                $reflectionClass = new ReflectionClass($fqn);

                if (is_a($fqn, EntityReader::class, true) && !$reflectionClass->isAbstract()) {
                    $result[$fqn::getEntityClassName()] = $fqn;
                }
            }
        }

        return $result;
    }

    public function getReader(string $className): ?EntityReader
    {
        return $this->locator->get($className);
    }

    public function getWriter(string $className): ?EntityWriter
    {
        return $this->locator->get($className);
    }
}
