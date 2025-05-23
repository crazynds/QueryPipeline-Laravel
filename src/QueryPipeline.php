<?php

namespace Crazynds\QueryPipeline;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as Builder2;
use Illuminate\Pipeline\Pipeline;

trait QueryPipeline
{
    private function reduceArray($array)
    {
        $carry = '';
        foreach ($array as $key => $valor) {
            if (gettype($valor) == 'array') {
                $valor = serialize($valor);
            }
            $carry = (empty($carry) ? '' : ($carry.',')).(gettype($key) == 'string' ? ($key.':') : '').str_replace([',', ':'], ['º', '§'], $valor);
        }

        return $carry;
    }

    private function getFormatedMiddlewares($stack)
    {
        $newStack = [];
        foreach ($stack as $key => $value) {
            if (gettype($key) == 'string') {
                if (gettype($value) == 'string') {
                    $newStack[] = $key.':'.$value;
                } elseif (gettype($value) == 'array' && count($value) > 0) {
                    $reduced = $this->reduceArray($value);
                    $newStack[] = $key.':'.$reduced;
                } else {
                    $newStack[] = $key;
                }
            } else {
                $newStack[] = $value;
            }
        }

        return $newStack;
    }

    public function runPipeline(Builder|Builder2 $oringinalQuery, array $data, array $stackMiddleware)
    {
        $newStack = $this->getFormatedMiddlewares($stackMiddleware);
        $oringinalQuery->where(function ($query) use ($data, $newStack, $oringinalQuery) {
            $query = app(Pipeline::class)
                ->send($data)
                ->through(array_reverse($newStack))
                ->then(function () use ($query) {
                    return $query;
                });
            $wheres = $query->getQuery()->wheres;
            $bindings = $query->getQuery()->bindings['where'];
            $joins = $query->getQuery()->joins;
            $joinsBindings = $query->getQuery()->bindings['join'];

            $query->getQuery()->wheres = [];
            $query->getQuery()->bindings['where'] = [];
            $query->where(function ($inQuery) use ($bindings, $wheres) {
                $inQuery->getQuery()->wheres = $wheres;
                $inQuery->addBinding($bindings, 'where');
            });

            $oringinalQuery->getQuery()->bindings['order'] = $query->getQuery()->bindings['order'];
            $oringinalQuery->getQuery()->orders = $query->getQuery()->orders;

            $oringinalQuery->getQuery()->bindings['join'] = $joinsBindings;
            $oringinalQuery->getQuery()->joins = $joins;
        });

        return $oringinalQuery;
    }
}
