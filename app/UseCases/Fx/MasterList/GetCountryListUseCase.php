<?php

declare(strict_types=1);

namespace App\UseCases\Fx\MasterList;

use App\Models\FxCountry;

class GetCountryListUseCase
{
    public function execute(): array
    {
        return FxCountry::where('deleted', false)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (FxCountry $c) => ['key' => $c->code, 'value' => $c->name])
            ->all();
    }
}
