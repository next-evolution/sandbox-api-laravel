<?php

declare(strict_types=1);

namespace App\UseCases\Fx\Country;

use App\Exceptions\NotFoundException;
use App\Models\FxCountry;

class GetCountryUseCase
{
    public function execute(string $code): array
    {
        $row = FxCountry::where('code', $code)->where('deleted', false)->first();

        if ($row === null) {
            throw new NotFoundException($code);
        }

        return $row->toDtoArray();
    }
}
