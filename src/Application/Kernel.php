<?php

namespace Autarco\JiraKpi\Application;

use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\CarbonMixinTrait;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        CarbonImmutable::mixin(CarbonMixinTrait::class);

        parent::boot();
    }
}
