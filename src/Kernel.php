<?php

namespace MdServer;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        if ($this->isPhar()) {
            return sys_get_temp_dir() . '/md-server/cache/' . $this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if ($this->isPhar()) {
            return sys_get_temp_dir() . '/md-server/log';
        }

        return parent::getLogDir();
    }

    private function isPhar(): bool
    {
        return str_starts_with($this->getProjectDir(), 'phar://');
    }
}
