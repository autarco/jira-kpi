<?php

namespace Autarco\JiraKpi\Tests;

use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;

abstract class AbstractTestCase extends MockeryTestCase
{
    protected function mockIssue(string $key, string $created): MockInterface & Issue
    {
        $issue = mock(Issue::class);

        $issue->allows()->getKey()->andReturn($key);
        $issue->allows()->getCreated()->andReturn(CarbonImmutable::make($created));

        return $issue;
    }
}
