<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Controllers\V1;

use Illuminate\Routing\Controller;

use Modules\Issue\app\Models\IssueCategory;
use Modules\Issue\app\Http\Resources\V1\IssueCategoryResource;


class IssueCategoryController extends Controller
{

    public function index()
    {
        return IssueCategoryResource::collection(

            IssueCategory::query()
                ->where('is_active', true)
                ->get()

        );
    }

}