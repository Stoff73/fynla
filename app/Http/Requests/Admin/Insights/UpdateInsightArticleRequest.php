<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Insights;

class UpdateInsightArticleRequest extends StoreInsightArticleRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $field => $constraints) {
            if (! in_array('nullable', $constraints, true) && ! in_array('sometimes', $constraints, true)) {
                array_unshift($constraints, 'sometimes');
                $rules[$field] = $constraints;
            }
        }

        return $rules;
    }
}
