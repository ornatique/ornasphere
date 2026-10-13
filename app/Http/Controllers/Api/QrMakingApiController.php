<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Company\QrMakingController;
use App\Models\{Company, MarketingQr};
use Illuminate\Http\Request;

class QrMakingApiController extends Controller
{
    public function handle(Request $request)
    {
        $user=$request->user();
        abort_unless($user && $user->company_id,403);
        $company=Company::findOrFail($user->company_id);
        $operation=$request->route('operation');
        $action=match($operation) {
            'store'=>'create', 'update'=>'edit', 'destroy'=>'delete',
            'preview'=>$request->filled('id') ? 'edit' : 'create',
            default=>'view',
        };
        abort_unless($user->hasRole('company_admin') || $user->hasAnyPermission(['qr-making-'.$action]),403);
        $web=app(QrMakingController::class);
        $id=(int)$request->route('id');
        if ($operation==='index') {
            $request->validate(['search'=>'nullable|string|max:255','per_page'=>'nullable|integer|min:1|max:100']);
            $rows=MarketingQr::where('company_id',$company->id)->with('creator:id,name')
                ->when($request->filled('search'),fn($q)=>$q->where('title','like','%'.$request->input('search').'%'))
                ->latest()->paginate($request->integer('per_page',10));
            $rows->through(fn($qr)=>$this->data($qr));
            return response()->json(['success'=>true,'data'=>$rows]);
        }
        if ($operation==='preview') return $web->preview($request,$company->slug);
        if ($operation==='store' || $operation==='update') {
            $qr=$web->persist($request,$company->slug,$operation==='update' ? $id : null);
            return response()->json(['success'=>true,'data'=>$this->data($qr)],$operation==='store' ? 201 : 200);
        }
        if ($operation==='destroy') {
            $web->deleteQr($company->slug,$id);
            return response()->json(['success'=>true,'message'=>'QR deleted.']);
        }
        if (in_array($operation,['svg','pdf','image'],true)) return $web->{$operation}($company->slug,$id);
        $qr=MarketingQr::where('company_id',$company->id)->findOrFail($id);
        return response()->json(['success'=>true,'data'=>$this->data($qr)]);
    }

    private function data(MarketingQr $qr): array
    {
        return [
            'id'=>$qr->id,'title'=>$qr->title,'type'=>$qr->type,'size'=>(int)$qr->size,
            'web_url'=>$qr->web_url,'android_url'=>$qr->android_url,'ios_url'=>$qr->ios_url,
            'with_logo'=>(bool)$qr->logo_path,'created_by'=>$qr->created_by,
            'creator_name'=>$qr->creator?->name,'created_at'=>$qr->created_at,'updated_at'=>$qr->updated_at,
            'qr_url'=>$qr->type==='web' ? $qr->web_url : route('qr.open',$qr->token),
            'image_url'=>url('api/qr-making/'.$qr->id.'/image'),
            'svg_url'=>url('api/qr-making/'.$qr->id.'/svg'),
            'pdf_url'=>url('api/qr-making/'.$qr->id.'/pdf'),
        ];
    }
}
