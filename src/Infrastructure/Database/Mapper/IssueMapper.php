<?php

namespace Autarco\JiraKpi\Infrastructure\Database\Mapper;

use Doctrine\DBAL\Query\QueryBuilder;
use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Autarco\JiraKpi\Domain\Model\Issue\IssueStatus;
use Autarco\JiraKpi\Domain\Model\Issue\IssueType;
use Autarco\JiraKpi\Domain\Repository\Query\BugsReportedBetweenQuery;
use Autarco\JiraKpi\Domain\Repository\Query\TransitionedToStatusBetweenQuery;

/**
 * @extends BaseEntityMapper<Issue>
 */
class IssueMapper extends BaseEntityMapper
{
    public static function getEntityClassName(): string
    {
        return Issue::class;
    }

    protected function idField(): string
    {
        return 'key';
    }

    protected function whereDoneBetween(QueryBuilder $sqlBuilder, TransitionedToStatusBetweenQuery $query): void
    {
        $sqlBuilder->join('i', 'transition', 't', 'i.`key` = t.issue');
        $sqlBuilder->where('t.`to` = ' . $this->toSqlParam($sqlBuilder, IssueStatus::DONE));
        $sqlBuilder->andWhere('t.transitioned >= ' . $this->toSqlParam($sqlBuilder, $query->after));
        $sqlBuilder->andWhere('t.transitioned < ' . $this->toSqlParam($sqlBuilder, $query->before));
    }

    protected function whereBugsReportedBetween(QueryBuilder $sqlBuilder, BugsReportedBetweenQuery $query): void
    {
        $sqlBuilder->where('i.type = ' . $this->toSqlParam($sqlBuilder, IssueType::BUG));
        $sqlBuilder->andWhere('i.created >= ' . $this->toSqlParam($sqlBuilder, $query->after));
        $sqlBuilder->andWhere('i.created < ' . $this->toSqlParam($sqlBuilder, $query->before));
        $sqlBuilder->andWhere('i.status != ' . $this->toSqlParam($sqlBuilder, IssueStatus::CANCELLED));
    }
}
