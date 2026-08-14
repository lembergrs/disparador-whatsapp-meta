<?php

namespace Services;

use InvalidArgumentException;

class EmbeddedSignupOnboardingMode
{
    public const TRADITIONAL = 'traditional';
    public const COEXISTENCE = 'coexistence';

    public static function normalize($mode)
    {
        $mode = trim((string) $mode);
        if($mode === ''){
            return self::TRADITIONAL;
        }

        if(!in_array($mode, [self::TRADITIONAL, self::COEXISTENCE], true)){
            throw new InvalidArgumentException('Modalidade de onboarding inválida.');
        }

        return $mode;
    }

    public static function expectedFinishEvent($mode)
    {
        return self::normalize($mode) === self::COEXISTENCE
            ? 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING'
            : 'FINISH';
    }

    public static function acceptsFinishEvent($mode, $event)
    {
        return hash_equals(self::expectedFinishEvent($mode), (string) $event);
    }

    public static function fromFinishEvent($event)
    {
        $event = trim((string) $event);
        if($event === 'FINISH'){
            return self::TRADITIONAL;
        }
        if($event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING'){
            return self::COEXISTENCE;
        }
        throw new InvalidArgumentException('Evento de conclusão inválido para o onboarding Meta.');
    }
}
