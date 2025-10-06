// app/Http/Controllers/AffRedirectController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class AffRedirectController extends Controller
{
    public function out(string $pid, Request $req)
    {
        // プロダクトIDからアフィリエイトURLを復元（例：クエリ or 生成）
        // 既に詳細ページで $affUrl を持っているなら署名付きURLで受け取るのが安全
        $affUrl = $req->query('u'); // 例：?u=URLエンコード済み
        if (!$affUrl || !filter_var($affUrl, FILTER_VALIDATE_URL)) {
            abort(404);
        }

        // UTMを付与（既に付いてたら重複しないように）
        $utm = [
            'utm_source'   => 'duga-adult.com',
            'utm_medium'   => 'affiliate',
            'utm_campaign' => 'product',
            'utm_content'  => $pid,
        ];
        $glue = parse_url($affUrl, PHP_URL_QUERY) ? '&' : '?';
        $target = $affUrl.$glue.http_build_query(array_diff_key($utm, array_flip(['utm_source','utm_medium','utm_campaign','utm_content'])));

        // DBへ簡易ログ（SQLite可）
        try {
            DB::table('affiliate_clicks')->insert([
                'pid'        => $pid,
                'ip'         => $req->ip(),
                'ua'         => substr((string)$req->userAgent(), 0, 500),
                'referer'    => substr((string)$req->headers->get('referer', ''), 0, 500),
                'created_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('aff_click_log_failed', ['e'=>$e->getMessage()]);
        }

        // 302でリダイレクト
        return redirect()->away($target, 302);
    }
}