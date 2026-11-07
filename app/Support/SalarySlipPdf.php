<?php

namespace App\Support;

use App\Models\SalarySlip;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** One-page salary slip (A4) with dompdf, styled like the quotations. Only for payroll viewers and the employee. */
final class SalarySlipPdf
{
    public static function render(SalarySlip $slip): string
    {
        $slip->loadMissing(['employee.department', 'employee.jobTitle', 'run', 'items']);
        $html = view('pdf.salary-slip', [
            'slip' => $slip,
            'company' => Billing::settings(),
            'fontDir' => str_replace('\\', '/', resource_path('fonts')),
        ])->render();

        $dompdf = new Dompdf(self::options());
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->addInfo('Title', self::filename($slip));
        $dompdf->render();

        return $dompdf->output();
    }

    public static function filename(SalarySlip $slip): string
    {
        return 'Salary slip '.$slip->employee->code.' '.$slip->period.'.pdf';
    }

    public static function response(string $bytes, string $filename): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $filename, Str::ascii($filename)),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function options(): Options
    {
        $fontCache = storage_path('fonts');
        File::ensureDirectoryExists($fontCache);

        return new Options([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'defaultMediaType' => 'print',
            'dpi' => 96,
            'chroot' => [resource_path('fonts')],
            'fontDir' => $fontCache,
            'fontCache' => $fontCache,
            'tempDir' => $fontCache,
        ]);
    }
}
