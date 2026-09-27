<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER','HR','ACCOUNTS'])->isNotEmpty(),403);
        $institutions=DB::table('tagore_institutions')->whereIn('id',$ids)->where('status','active')->orderBy('display_name')->get(['id','display_name']);
        $documents=DB::table('tagore_document_records')->whereIn('institution_id',$ids)->orderByDesc('id')->limit(200)->get();
        return view('tagore.documents.index',compact('institutions','documents'));
    }

    public function upload(Request $request)
    {
        [$roles,$ids]=$this->context($request);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER','HR','ACCOUNTS'])->isNotEmpty(),403);
        $d=$request->validate([
            'institution_id'=>'required|integer','title'=>'required|string|max:190','document_type'=>'required|string|max:100',
            'owner_type'=>'nullable|string|max:50','owner_id'=>'nullable|integer','access_json'=>'nullable|json',
            'file'=>'required|file|max:20480'
        ]);
        abort_unless(in_array((int)$d['institution_id'],$ids,true),403);
        $path=$request->file('file')->store('tagore-documents/'.$d['institution_id'],'private');
        $documentId=DB::transaction(function()use($d,$request,$path){
            $existing=DB::table('tagore_document_records')->where('institution_id',$d['institution_id'])->where('title',$d['title'])->where('owner_type',$d['owner_type']??null)->where('owner_id',$d['owner_id']??null)->lockForUpdate()->first();
            $version=$existing ? ((int)$existing->version+1) : 1;
            if($existing){
                DB::table('tagore_document_records')->where('id',$existing->id)->update([
                    'document_type'=>$d['document_type'],'path'=>$path,'version'=>$version,'access_json'=>$d['access_json']??null,'status'=>'active','updated_at'=>now()
                ]);
                $documentId=$existing->id;
            } else {
                $documentId=DB::table('tagore_document_records')->insertGetId([
                    'institution_id'=>$d['institution_id'],'owner_type'=>$d['owner_type']??null,'owner_id'=>$d['owner_id']??null,
                    'title'=>$d['title'],'document_type'=>$d['document_type'],'path'=>$path,'version'=>$version,
                    'access_json'=>$d['access_json']??null,'status'=>'active','created_at'=>now(),'updated_at'=>now()
                ]);
            }
            DB::table('tagore_document_versions')->insert([
                'document_id'=>$documentId,'version'=>$version,'path'=>$path,'original_name'=>$request->file('file')->getClientOriginalName(),
                'mime_type'=>$request->file('file')->getClientMimeType(),'size_bytes'=>$request->file('file')->getSize(),
                'uploaded_by'=>$request->user()->id,'sha256'=>hash_file('sha256',$request->file('file')->getRealPath()),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            return $documentId;
        });
        return back()->with('success','Document uploaded (#'.$documentId.').');
    }

    public function download(Request $request,int $id)
    {
        [$roles,$ids]=$this->context($request);
        $document=DB::table('tagore_document_records')->where('id',$id)->whereIn('institution_id',$ids)->first(); abort_unless($document,404);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER','HR','ACCOUNTS'])->isNotEmpty(),403);
        abort_unless(Storage::disk('private')->exists($document->path),404);
        return Storage::disk('private')->download($document->path,$document->title);
    }

    public function versions(Request $request,int $id)
    {
        [$roles,$ids]=$this->context($request);
        $document=DB::table('tagore_document_records')->where('id',$id)->whereIn('institution_id',$ids)->first(); abort_unless($document,404);
        abort_unless($roles->contains('OWNER') || $roles->intersect(['PRINCIPAL','COORDINATOR','TEACHER','HR','ACCOUNTS'])->isNotEmpty(),403);
        return response()->json(DB::table('tagore_document_versions')->where('document_id',$id)->orderByDesc('version')->get());
    }

    private function context(Request $request): array
    {
        $userId=(int)$request->user()->id;
        $roles=DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
        $ids=$roles->contains('OWNER') ? DB::table('tagore_institutions')->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all() : DB::table('tagore_user_roles')->where('user_id',$userId)->where('status','active')->whereNotNull('institution_id')->pluck('institution_id')->map(fn($id)=>(int)$id)->unique()->values()->all();
        return [$roles,$ids];
    }
}
