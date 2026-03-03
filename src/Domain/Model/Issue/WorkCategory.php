<?php

namespace Autarco\JiraKpi\Domain\Model\Issue;

enum WorkCategory
{
    case ROADMAP;
    case REQUEST;
    case BUG;
    case TECH;
}
