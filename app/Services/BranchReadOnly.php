<?php
namespace App\Services;

use Illuminate\Http\Request;

class BranchReadOnly
{
    public const MESSAGE = 'Other branches are read-only. Log in to that branch to make changes.';

    public static function canWrite($user, int $companyId): bool
    {
        return $user && (int) $user->company_id === $companyId;
    }

    public static function isReadRequest(Request $request): bool
    {
        $action = $request->route()?->getAction('uses') ?? '';
        $method = is_string($action) ? substr($action, strrpos($action, '@') !== false ? strrpos($action, '@') + 1 : 0) : '';
        if ($method === 'exportPdf' && str_contains($action, 'StockGalleryApiController') && $request->isMethod('POST')) return true;
        // Some legacy GET endpoints create receipts or update printing state.
        if (preg_match('/edit|create|store|update|destroy|delete|reset|toggle|activate|finalize|generate|reserve|printDirect|printPdf/i', $method)) return false;
        if ($method === 'show' && str_contains($request->route()?->getActionName() ?? '', 'JobworkReceive')) return false;
        return $request->isMethod('GET') || $request->isMethod('HEAD');
    }

    public static function denied()
    {
        return response()->json(['success'=>false, 'code'=>'BRANCH_READ_ONLY', 'message'=>self::MESSAGE],403);
    }

    public static function protectRows(array $payload, $user): array
    {
        foreach ($payload['data'] ?? [] as $key=>$row) {
            if (!is_array($row) || !isset($row['company_id'])) continue;
            $writable = self::canWrite($user,(int)$row['company_id']);
            $row['can_manage'] = $writable;
            if (!$writable) {
                $row['can_edit'] = false;
                $row['can_delete'] = false;
                foreach (['action','actions'] as $field) {
                    if (is_string($row[$field] ?? null)) {
                        $dom = new \DOMDocument;
                        @$dom->loadHTML('<?xml encoding="UTF-8"><div id="branch-actions">'.$row[$field].'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        foreach ($dom->getElementsByTagName('*') as $node) {
                            if (!in_array($node->tagName,['a','button','input','form'])) continue;
                            $label=trim($node->textContent);
                            if ($node->tagName==='a' && preg_match('/^(view|pdf|view pdf|history|download)/i',$label) && !preg_match('/edit|update|delete/i',$node->getAttribute('href'))) continue;
                            $node->removeAttribute('href'); $node->removeAttribute('onclick'); $node->removeAttribute('action');
                            $node->setAttribute('disabled','disabled'); $node->setAttribute('aria-disabled','true');
                            $node->setAttribute('title',self::MESSAGE);
                            $node->setAttribute('class','btn btn-sm btn-secondary disabled branch-read-only');
                        }
                        $root=$dom->getElementById('branch-actions'); $html='';
                        if ($root) foreach ($root->childNodes as $child) $html.=$dom->saveHTML($child);
                        $row[$field]=$html;
                    } elseif (is_array($row[$field] ?? null)) {
                        foreach ($row[$field] as $action=>&$detail) {
                            if (preg_match('/edit|update|delete|remove|create|save|reset/i',(string)$action)) $detail=['enabled'=>false,'reason'=>self::MESSAGE];
                        }
                        unset($detail);
                    }
                }
            }
            $payload['data'][$key]=$row;
        }
        return $payload;
    }
}
