<?php

namespace Services\Tasks;

class TaskRetryPolicy
{
    private const ATRASOS = [300, 900, 3600];

    public function atrasoSegundos($tentativa): int
    {
        $indice = max(0, min(count(self::ATRASOS) - 1, (int)$tentativa - 1));
        return self::ATRASOS[$indice];
    }

    public function proximaTentativa($tentativa, \DateTimeInterface $agora = null): \DateTimeImmutable
    {
        $base = $agora ? \DateTimeImmutable::createFromInterface($agora) : new \DateTimeImmutable('now');
        return $base->modify('+' . $this->atrasoSegundos($tentativa) . ' seconds');
    }
}
