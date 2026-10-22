<?php
namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\{Company, MarketingQr};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Endroid\QrCode\{QrCode, ErrorCorrectionLevel};
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Writer\{SvgWriter, PngWriter};
use Barryvdh\DomPDF\Facade\Pdf;

class QrMakingController extends Controller
{
    private function company(string $slug): Company { return Company::where('slug',$slug)->firstOrFail(); }
    private function record(Company $company, int $id): MarketingQr { return MarketingQr::where('company_id',$company->id)->findOrFail($id); }
    public function index(Request $request, string $slug) {
        $company = $this->company($slug);
        $companies = \App\Services\CompanyBranches::managementSelection($request, $company);
        $rows = MarketingQr::whereIn('company_id',$companies->keys())->with('creator')
            ->latest()->get();
        return view('company.qr_making.index',compact('company','rows','companies'));
    }
    public function create(string $slug) {
        $company = $this->company($slug); $qr = new MarketingQr(['size'=>250,'type'=>'web']);
        return view('company.qr_making.form',compact('company','qr'));
    }
    public function edit(string $slug, int $id) {
        $company=$this->company($slug); $qr=$this->record($company,$id);
        return view('company.qr_making.form',compact('company','qr'));
    }
    private function validated(Request $r, ?MarketingQr $qr = null): array {
        if (is_bool($r->input('with_logo')) || is_int($r->input('with_logo'))) {
            $r->merge(['with_logo'=>(string)(int)$r->input('with_logo')]);
        }
        return $r->validate([
            'title'=>'required|string|max:255', 'type'=>'required|in:web,app','size'=>'required|integer|in:50,100,150,250,350,500',
            'web_url'=>'exclude_unless:type,web|required|url:http,https|max:2048',
            'android_url'=>'exclude_unless:type,app|required|url:https|max:2048',
            'ios_url'=>'exclude_unless:type,app|required|url:https|max:2048',
            'with_logo'=>'required|in:0,1',
            'logo'=>[($r->input('with_logo') === '1' && !$qr?->logo_path) ? 'required' : 'nullable','image','mimes:jpg,jpeg,png,webp','max:2048','dimensions:max_width=4000,max_height=4000'],
        ]);
    }
    public function store(Request $r, string $slug) { return $this->save($r,$slug); }
    public function update(Request $r, string $slug, int $id) { return $this->save($r,$slug,$id); }
    private function save(Request $r, string $slug, ?int $id=null) {
        $this->persist($r,$slug,$id);
        return redirect()->route('company.qr-making.index',$slug);
    }
    public function persist(Request $r, string $slug, ?int $id=null): MarketingQr {
        $company=$this->company($slug); $qr=$id ? $this->record($company,$id) : new MarketingQr(['company_id'=>$company->id,'created_by'=>$r->user()->id,'token'=>(string)Str::uuid()]);
        $data=$this->validated($r,$qr); $old=$qr->logo_path; $new=null;
        try {
            $qr->fill(collect($data)->except(['with_logo','logo'])->all());
            if ($data['type']==='web') { $qr->android_url=null; $qr->ios_url=null; } else $qr->web_url=null;
            if ($data['with_logo']==='0') $qr->logo_path=null;
            elseif ($r->hasFile('logo')) $qr->logo_path=$new=$r->file('logo')->store('qr-logos/'.$company->id,'local');
            $this->renderQr($qr); // Validate rendering before saving the record.
            $qr->save();
        } catch (\Throwable $e) { if ($new) Storage::disk('local')->delete($new); throw $e; }
        if ($old && $old!==$qr->logo_path) Storage::disk('local')->delete($old);
        return $qr;
    }
    public function preview(Request $r,string $slug) {
        $company=$this->company($slug);
        $qr=$r->filled('id') ? $this->record($company,$r->integer('id')) : new MarketingQr(['token'=>(string)Str::uuid()]);
        $data=$this->validated($r,$qr); $qr->fill(collect($data)->except(['logo','with_logo'])->all());
        if ($data['with_logo']==='0') $qr->logo_path=null;
        return response()->json(['image'=>$this->renderQr($qr,false,$r->hasFile('logo') && $data['with_logo']==='1' ? $r->file('logo')->getRealPath() : null)->getDataUri()]);
    }
    public function renderQr(MarketingQr $qr, bool $png=false, ?string $logoPath=null) {
        $destination=$qr->type==='web' ? $qr->web_url : route('qr.open',$qr->token);
        $code=new QrCode(data:$destination, errorCorrectionLevel:ErrorCorrectionLevel::High,size:(int)$qr->size,margin:20);
        $path=$logoPath ?? ($qr->logo_path ? Storage::disk('local')->path($qr->logo_path) : null);
        if (!$path) return ($png ? new PngWriter() : new SvgWriter())->write($code);

        // Flatten transparent logos onto a padded white tile for both writers.
        // SVG does not support Endroid's punchoutBackground option.
        $source=imagecreatefromstring(file_get_contents($path));
        if (!$source) throw new \RuntimeException('Unable to read QR logo.');
        $tile=imagecreatetruecolor(400,400);
        $temporary=null;
        try {
            imagefill($tile,0,0,imagecolorallocate($tile,255,255,255));
            $scale=300/max(imagesx($source),imagesy($source));
            $width=max(1,(int)round(imagesx($source)*$scale));
            $height=max(1,(int)round(imagesy($source)*$scale));
            imagecopyresampled($tile,$source,(int)((400-$width)/2),(int)((400-$height)/2),0,0,$width,$height,imagesx($source),imagesy($source));
            $temporary=tempnam(sys_get_temp_dir(),'qr-logo-');
            if ($temporary===false || !imagepng($tile,$temporary)) throw new \RuntimeException('Unable to prepare QR logo.');
            $logo=new Logo(path:$temporary,resizeToWidth:max(12,(int)round($qr->size*.24)),punchoutBackground:false);
            return ($png ? new PngWriter() : new SvgWriter())->write($code,$logo);
        } finally {
            imagedestroy($source);
            imagedestroy($tile);
            if ($temporary && is_file($temporary)) unlink($temporary);
        }
    }
    public function svg(string $slug,int $id) {
        $qr=$this->record($this->company($slug),$id);
        return response($this->renderQr($qr)->getString(),200,['Content-Type'=>'image/svg+xml','Content-Disposition'=>'attachment; filename="qr-'.$qr->id.'.svg"']);
    }
    public function image(string $slug,int $id) {
        $qr=$this->record($this->company($slug),$id);
        return response($this->renderQr($qr)->getString(),200,['Content-Type'=>'image/svg+xml','Cache-Control'=>'private, no-cache']);
    }
    public function pdf(string $slug,int $id) {
        $company=$this->company($slug); $qr=$this->record($company,$id); $image=$this->renderQr($qr,true)->getDataUri();
        $filename=trim(preg_replace('/[^A-Za-z0-9 ._-]+/', '-', Str::ascii($qr->title)), ' .-');
        $filename=rtrim(substr($filename,0,150), ' .');
        if ($filename==='') $filename='qr-'.$qr->id;
        return Pdf::loadView('company.qr_making.pdf',compact('qr','image','company'))->download($filename.'.pdf');
    }
    public function destroy(string $slug,int $id) {
        $this->deleteQr($slug,$id);
        return back();
    }
    public function deleteQr(string $slug,int $id): void {
        $qr=$this->record($this->company($slug),$id); $path=$qr->logo_path; $qr->delete();
        if ($path) Storage::disk('local')->delete($path);
    }
    public function open(Request $r,string $token) {
        $qr=MarketingQr::where('token',$token)->firstOrFail();
        if ($qr->type==='web') return redirect()->away($qr->web_url)->header('Cache-Control','no-store');
        $agent=strtolower($r->userAgent() ?? '');
        if (str_contains($agent,'android')) return redirect()->away($qr->android_url)->header('Cache-Control','no-store');
        if (preg_match('/iphone|ipad|ipod/',$agent)) return redirect()->away($qr->ios_url)->header('Cache-Control','no-store');
        return response()->view('company.qr_making.landing',compact('qr'))->header('Cache-Control','no-store');
    }
}
