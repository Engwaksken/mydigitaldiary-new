<?php
namespace App\Http\Controllers\Api;
use App\Models\Note;
class NoteController extends ApiCrudController {
    protected string $model=Note::class;
    protected array $rules=['title'=>'required|string|max:255','category'=>'nullable|string|max:100','tags'=>'nullable|string|max:500','color'=>'nullable|in:sunshine,peach,mint,lavender,sky,rose','content'=>'nullable|string','is_pinned'=>'sometimes|boolean','is_favorite'=>'sometimes|boolean'];
}
