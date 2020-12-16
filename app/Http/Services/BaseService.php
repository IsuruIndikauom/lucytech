<?php

namespace App\Http\Services;

class BaseService
{
    protected const SUCCESS = 201;
    protected const ERROR = 400;

    protected function success()
    {
        return \Response::json([], self::SUCCESS);
    }

    protected function error($errors)
    {
        return \Response::json($errors, self::ERROR);

    }
}
