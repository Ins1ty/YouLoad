<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    private string $ytDlp = 'yt-dlp.exe';

    public function info(Request $request)
    {
        $url = $request->query('url');
        
        if (!$url) {
            return response()->json(['error' => 'URL is required'], 400);
        }

        $urlEncoded = urlencode($url);
        $command = "cmd /c \"$this->ytDlp --dump-json --no-download --socket-timeout 30 $urlEncoded\" 2>&1";
        
        $output = shell_exec($command);
        
        file_put_contents('C:\\Users\\Admin\\Desktop\\YouLoad\\backend\\debug.log', $command . " | output: " . strlen($output ?? 'NULL') . " bytes\n", FILE_APPEND);

        $lines = explode("\n", trim($output));
        $jsonLine = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '{')) {
                $jsonLine = $line;
                break;
            }
        }
        
        $data = json_decode($jsonLine, true);
        
        if (!$data) {
            file_put_contents('C:\\Users\\Admin\\Desktop\\YouLoad\\backend\\debug.log', "Failed to parse, output: " . ($output ?? 'NULL') . "\n", FILE_APPEND);
            return response()->json(['error' => 'Failed to parse video info'], 400);
        }
        
        $formats = $this->getAvailableFormats($data['formats'] ?? []);
        
        return response()->json([
            'title' => $data['title'] ?? 'Unknown',
            'thumbnail' => $data['thumbnail'] ?? null,
            'duration' => $data['duration'] ?? 0,
            'formats' => $formats,
        ]);
    }

    public function download(Request $request)
    {
        set_time_limit(300);
        
        $url = $request->input('url');
        $type = $request->input('type', 'mp4');
        $quality = $request->input('quality');
        
        if (!$url) {
            return response()->json(['error' => 'URL is required'], 400);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'yt_');
        
        if ($type === 'mp3') {
            $extension = 'mp3';
            $args = '--extract-audio --audio-format mp3 --audio-quality 256k';
        } else {
            $extension = 'mp4';
            if ($quality && $quality !== 'best') {
                $args = "-f best[height<=" . (int)$quality . "]";
            } else {
                $args = '-f best[height<=720]';
            }
        }

        $outputFile = $tempFile . '.' . $extension;
        
        $code = 0;
        $command = $this->ytDlp . ' ' . $args . ' --no-warnings --socket-timeout 60 -o "' . $outputFile . '" "' . $url . '"';
        
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open($command, $descriptors, $pipes);
        
        if (!is_resource($process)) {
            @unlink($tempFile);
            return response()->json(['error' => 'Download failed: cannot start process'], 500);
        }
        
        fclose($pipes[0]);
        stream_copy_to_stream($pipes[1], fopen('php://stderr', 'w'));
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $code = proc_close($process);
        
        if ($code !== 0 || !file_exists($outputFile)) {
            @unlink($tempFile);
            return response()->json(['error' => 'Download failed'], 500);
        }

        $filename = basename($outputFile);
        
        return response()->download($outputFile, $filename, [
            'Content-Type' => 'application/octet-stream',
        ])->deleteFileAfterSend(true);
    }

    private function getAvailableFormats(array $formats): array
    {
        $videoFormats = [];
        
        foreach ($formats as $format) {
            if (!empty($format['vcodec']) && $format['vcodec'] !== 'none') {
                $height = $format['height'] ?? null;
                if ($height) {
                    $videoFormats[$height] = $height . 'p';
                }
            }
        }

        krsort($videoFormats, SORT_NUMERIC);
        
        return array_values($videoFormats);
    }
}