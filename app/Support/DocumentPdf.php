<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Quote;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Quote and invoice PDFs (A4), rendered from resources/views/pdf/document.blade.php with dompdf.
 * Remote resources are disabled: the template only reads the bundled fonts in resources/fonts.
 */
final class DocumentPdf
{
    /**
     * Sizes tried in turn until the document fits on one A4 page: the template is drawn at full size,
     * then every pt/px measure in it is scaled down. Smaller than the last step, it is printed anyway.
     */
    private const FIT_SCALES = [1.0, 0.94, 0.88, 0.82, 0.76, 0.7, 0.64, 0.58, 0.54];

    /** Long quotes: item detail lines are dropped and rows tightened, then the same shrink steps are tried. */
    private const COMPACT_SCALES = [0.6, 0.56, 0.52, 0.48, 0.45];

    /** Renders the document as it is now and returns the PDF bytes, always on a single page. */
    public static function render(Quote|Invoice $doc): string
    {
        $doc->loadMissing(['items', 'client', ...($doc instanceof Invoice ? ['payments', 'quote'] : [])]);

        $html = view('pdf.document', [
            'doc' => $doc,
            'company' => Billing::settings(),
            'isInvoice' => $doc instanceof Invoice,
            'terms' => Billing::termsFor($doc::KIND),
            // A quote without its own note gets the standard quotation note from settings.
            'notes' => $doc->notes ?: ($doc instanceof Quote ? Billing::settings()['quote_notes'] : null),
            'fontDir' => str_replace('\\', '/', resource_path('fonts')),
        ])->render();
        $compactHtml = str_replace('<body>', '<body class="compact">', $html);

        $attempts = [...array_map(fn (float $s) => [$html, $s], self::FIT_SCALES), ...array_map(fn (float $s) => [$compactHtml, $s], self::COMPACT_SCALES)];
        foreach ($attempts as [$candidate, $scale]) {
            $dompdf = new Dompdf(self::options());
            $dompdf->loadHtml(self::scaled($candidate, $scale), 'UTF-8');
            $dompdf->setPaper('A4');
            $dompdf->addInfo('Title', self::title($doc));
            $dompdf->addInfo('Author', Billing::settings()['company_name']);
            $dompdf->render();

            if ($dompdf->getCanvas()->get_page_count() <= 1) {
                break;
            }
        }

        if ($dompdf->getCanvas()->get_page_count() > 1) {
            $canvas = $dompdf->getCanvas();
            $canvas->page_text($canvas->get_width() - 92, $canvas->get_height() - 37, 'Page {PAGE_NUM} of {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('Manrope'), 7.5, [0.53, 0.47, 0.48]);
        }

        return $dompdf->output();
    }

    /** Scales every pt and px measure inside <style> blocks and style="" attributes. */
    private static function scaled(string $html, float $scale): string
    {
        if ($scale === 1.0) {
            return $html;
        }
        $shrink = fn (array $m) => round((float) $m[1] * $scale, 2).$m[2];

        $html = preg_replace_callback('#<style>(.*?)</style>#s', fn (array $block) => '<style>'.preg_replace_callback('/(\d+(?:\.\d+)?)(pt|px)\b/', $shrink, $block[1]).'</style>', $html);

        return preg_replace_callback('/style="([^"]*)"/', fn (array $attr) => 'style="'.preg_replace_callback('/(\d+(?:\.\d+)?)(pt|px)\b/', $shrink, $attr[1]).'"', $html);
    }

    /**
     * Saves the PDF that is being emailed on the private disk, points the document at it and
     * returns its path. Every send keeps its own timestamped copy.
     */
    public static function storeSentCopy(Quote|Invoice $doc): string
    {
        $path = sprintf('documents/%ss/%s/%s-%s.pdf', $doc::KIND, $doc->number, $doc->number, now(config('zairon.timezone'))->format('Ymd-His'));
        Storage::disk('local')->put($path, self::render($doc));
        $doc->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    /** A private, uncached PDF response; downloaded as `$filename`, or shown in the browser when `$inline`. */
    public static function response(string $bytes, string $filename, bool $inline = false): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $filename, Str::ascii($filename)),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** e.g. "Invoice ZRON-20261016143052123.pdf" */
    public static function filename(Quote|Invoice $doc): string
    {
        return self::title($doc).'.pdf';
    }

    private static function title(Quote|Invoice $doc): string
    {
        return ucfirst($doc::KIND).' '.$doc->number;
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
