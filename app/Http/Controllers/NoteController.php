<?php
namespace App\Http\Controllers;
use App\Models\Note;
use Illuminate\Http\Request;
class NoteController extends CrudController {
    protected string $model=Note::class;
    protected string $routeName='notes';
    protected string $title='Note';
    protected string $icon='fa-solid fa-note-sticky';
    protected string $accent='amber';
    protected array $fields=[
        ['name'=>'title','label'=>'Title','type'=>'text','required'=>true],
        ['name'=>'category','label'=>'Category','type'=>'text'],
        ['name'=>'tags','label'=>'Tags','type'=>'text','placeholder'=>'e.g. work, ideas, personal'],
        ['name'=>'color','label'=>'Sticky note color','type'=>'select','options'=>['sunshine'=>'Sunshine yellow','peach'=>'Warm peach','mint'=>'Fresh mint','lavender'=>'Soft lavender','sky'=>'Clear sky','rose'=>'Rose pink'],'default'=>'sunshine'],
        ['name'=>'content','label'=>'Note','type'=>'textarea'],
        ['name'=>'is_pinned','label'=>'Pinned','type'=>'checkbox'],
        ['name'=>'is_favorite','label'=>'Favourite','type'=>'checkbox'],
    ];
    protected array $rules=['title'=>'required|string|max:255','category'=>'nullable|string|max:100','tags'=>'nullable|string|max:500','color'=>'nullable|in:sunshine,peach,mint,lavender,sky,rose','content'=>'nullable|string','is_pinned'=>'sometimes|boolean','is_favorite'=>'sometimes|boolean'];
    public function index(Request $request)
    {
        $query = Note::where('user_id', $request->user()->id)
            ->where('is_archived', false);

        return $this->renderIndex(
            $request,
            $query,
            [],
            fn ($query) => $query
                ->orderByDesc('is_pinned')
                ->orderByDesc('is_favorite')
                ->orderByDesc('updated_at')
        );
    }

    protected function stats(Request $r): array { $q=Note::where('user_id',$r->user()->id)->where('is_archived',false); return [
        ['label'=>'Notes','value'=>(string)(clone $q)->count(),'icon'=>'fa-solid fa-note-sticky','color'=>'amber'],
        ['label'=>'Pinned','value'=>(string)(clone $q)->where('is_pinned',true)->count(),'icon'=>'fa-solid fa-thumbtack','color'=>'orange'],
        ['label'=>'Favourites','value'=>(string)(clone $q)->where('is_favorite',true)->count(),'icon'=>'fa-solid fa-star','color'=>'yellow'],
    ]; }
}
