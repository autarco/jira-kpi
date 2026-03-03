<?php

namespace Autarco\JiraKpi\Infrastructure\Database\Mapper;

use Doctrine\DBAL\Query\QueryBuilder;
use Autarco\JiraKpi\Domain\Model\Issue\IssueStatus;
use Autarco\JiraKpi\Domain\Model\Issue\IssueTransition;
use Autarco\JiraKpi\Domain\Repository\Query\EarliestTransitionQuery;
use Autarco\JiraKpi\Domain\Repository\Query\FirstTransitionAfterQuery;
use Autarco\JiraKpi\Domain\Repository\Query\LastTransitionBeforeQuery;
use Autarco\JiraKpi\Domain\Repository\Query\LatestTransitionQuery;
use Autarco\JiraKpi\Domain\Repository\Query\TransitionedBetweenQuery;
use Autarco\JiraKpi\Domain\Repository\Query\TransitionedFromStatusBetweenQuery;
use Autarco\JiraKpi\Domain\Repository\Query\TransitionedToStatusBetweenQuery;

/**
 * @extends BaseEntityMapper<IssueTransition>
 */
class IssueTransitionMapper extends BaseEntityMapper
{
    public static function getEntityClassName(): string
    {
        return IssueTransition::class;
    }

    protected function tableName(): string
    {
        return 'transition';
    }

    protected function idField(): string
    {
        return 'externalId';
    }

    protected function whereBetween(QueryBuilder $sqlBuilder, TransitionedBetweenQuery $query): void
    {
        $sqlBuilder->where('transitioned >= ' . $this->toSqlParam($sqlBuilder, $query->after));
        $sqlBuilder->andWhere('transitioned < ' . $this->toSqlParam($sqlBuilder, $query->before));
        $sqlBuilder->orderBy('transitioned', 'ASC');
    }

    protected function whereToStatusBetween(QueryBuilder $sqlBuilder, TransitionedToStatusBetweenQuery $query): void
    {
        $sqlBuilder->where('`to` = ' . $this->toSqlParam($sqlBuilder, $query->status));
        $sqlBuilder->andWhere('transitioned >= ' . $this->toSqlParam($sqlBuilder, $query->after));
        $sqlBuilder->andWhere('transitioned < ' . $this->toSqlParam($sqlBuilder, $query->before));
        $sqlBuilder->orderBy('transitioned', 'ASC');
    }

    protected function whereFromStatusBetween(QueryBuilder $sqlBuilder, TransitionedFromStatusBetweenQuery $query): void
    {
        $sqlBuilder->where('`from` = ' . $this->toSqlParam($sqlBuilder, $query->status));
        $sqlBuilder->andWhere('transitioned >= ' . $this->toSqlParam($sqlBuilder, $query->after));
        $sqlBuilder->andWhere('transitioned < ' . $this->toSqlParam($sqlBuilder, $query->before));
        $sqlBuilder->orderBy('transitioned', 'ASC');

        if ($query->ignoreToToDo) {
            $sqlBuilder->andWhere('`to` != ' . $this->toSqlParam($sqlBuilder, IssueStatus::TO_DO));
        }
    }

    protected function whereLatest(QueryBuilder $sqlBuilder, LatestTransitionQuery $query): void
    {
        $sqlBuilder->where('issue = ' . $this->toSqlParam($sqlBuilder, $query->issue));
        $sqlBuilder->andWhere('`to` = ' . $this->toSqlParam($sqlBuilder, $query->to));
        $sqlBuilder->orderBy('transitioned', 'DESC');
    }

    protected function whereEarliest(QueryBuilder $sqlBuilder, EarliestTransitionQuery $query): void
    {
        $sqlBuilder->where('issue = ' . $this->toSqlParam($sqlBuilder, $query->issue));
        $sqlBuilder->andWhere('`to` = ' . $this->toSqlParam($sqlBuilder, $query->to));
        $sqlBuilder->orderBy('transitioned', 'ASC');
    }

    protected function whereLastBefore(QueryBuilder $sqlBuilder, LastTransitionBeforeQuery $query): void
    {
        $subSqlBuilder = $this->createSqlBuilder();
        $subSqlBuilder->select('issue', 'MAX(transitioned)');
        $subSqlBuilder->groupBy('issue');
        $subSqlBuilder->where('transitioned < ' . $this->toSqlParam($sqlBuilder, $query->before));

        $subSql = $subSqlBuilder->getSQL();

        $sqlBuilder->join('t', 'issue', 'i', 't.issue = i.`key`');
        $sqlBuilder->where('(t.issue, transitioned) IN (' . $subSql . ')');
        $sqlBuilder->orderBy('`key`', 'ASC');
    }

    protected function whereFirstAfter(QueryBuilder $sqlBuilder, FirstTransitionAfterQuery $query): void
    {
        $subSqlBuilder = $this->createSqlBuilder();
        $subSqlBuilder->select('issue', 'MIN(transitioned)');
        $subSqlBuilder->groupBy('issue');
        $subSqlBuilder->where('transitioned >= ' . ($param = $this->toSqlParam($sqlBuilder, $query->after)));

        $subSql = $subSqlBuilder->getSQL();

        $sqlBuilder->join('t', 'issue', 'i', 't.issue = i.`key`');
        $sqlBuilder->where('i.created < ' . $param);
        $sqlBuilder->andWhere('(t.issue, transitioned) IN (' . $subSql . ')');
        $sqlBuilder->orderBy('`key`', 'ASC');
    }
}
