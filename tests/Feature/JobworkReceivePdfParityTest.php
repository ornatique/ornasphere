<?php
namespace Tests\Feature;

use Tests\TestCase;

class JobworkReceivePdfParityTest extends TestCase
{
    public function test_api_and_web_weight_aliases_produce_identical_pdf_markup(): void
    {
        $item=(object)['item_name'=>'Luckey'];
        $issueItem=(object)['net_wt'=>140,'item'=>$item];
        $row=(object)['voucher_no'=>'JI26-2','created_at'=>now(),'jobwork_date'=>now(),
            'jobWorker'=>(object)['name'=>'Meet'],'productionStep'=>(object)['name'=>'Tar'],
            'items'=>collect([$issueItem]),'net_wt_sum'=>140];
        $receive=(object)['created_at'=>now(),'updated_at'=>now(),'receive_date'=>now(),'remarks'=>null,
            'items'=>collect([(object)['jobworkIssueItem'=>$issueItem,'item'=>$item,
                'receive_gross_wt'=>149.4,'receive_net_wt'=>149.4,'other_wt'=>0,'receive_fine_wt'=>138.942,'remarks'=>null]])];
        $workerIssueVouchers=collect([$row]);
        $web=view('company.jobwork_receive.pdf.show',compact('row','receive','workerIssueVouchers'))->render();
        unset($row->net_wt_sum);
        $row->issue_net_wt_sum=140;
        $api=view('company.jobwork_receive.pdf.show',compact('row','receive','workerIssueVouchers'))->render();
        $this->assertSame($web,$api);
        $this->assertStringContainsString('140.000 gram',$api);
        $this->assertStringContainsString('9.400 gram',$api);
        $this->assertStringContainsString('149.400 gram',$api);
    }
}
