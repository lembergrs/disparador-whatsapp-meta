<?php

namespace Services;

use Exception;

class EmbeddedSignupAttemptCoordinator
{
    public function aguardarFinish(callable $carregarTentativa, $timeoutMs = 3000, $intervalMs = 100)
    {
        $deadline = microtime(true) + max(0, (int) $timeoutMs) / 1000;
        $ultimaTentativa = null;

        do{
            $tentativa = call_user_func($carregarTentativa);

            if(is_array($tentativa)){
                $ultimaTentativa = $tentativa;

                if(!empty($tentativa['used_at'])){
                    throw new Exception('Este retorno da Meta já foi utilizado.');
                }

                if(!empty($tentativa['finish'])){
                    return $tentativa;
                }
            }

            if(microtime(true) >= $deadline){
                break;
            }

            usleep(max(1, (int) $intervalMs) * 1000);
        }while(true);

        return $ultimaTentativa;
    }
}
