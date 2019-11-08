<?php

include_once 'vendor/autoload.php';

global $logger;
$logger = new \Katzgrau\KLogger\Logger('php://stdout');

$arg1 = $argv[1];
$arg2 = $argv[2];

if (file_exists($arg1))
    main(is_file($arg1) ? array($arg1) : glob($arg1 . '/*.ncm'), $arg2);
else if (!isset($arg1))
    $logger->error('main.php [*.ncm file/dir] <output path>');
else
    $logger->error('file/dir does not exist!');

function main(array $files, string $output = null) {
    global $logger;
    $logger->info('Start convert musics.');

    $time = microtime(true);
    $pool = \Spatie\Async\Pool::create();

    foreach ($files as $index => $file) {
        $logger->info("Start processing {{$index}}. {$file}");
        $pool[] = async(function () use ($file, $output) {
            /*$converter = new Converter();
            $converter->convert($file);
            sleep(12);*/
            $converter = new class {

                const CORE_KEY = 'hzHRAmso5kInbaxW';
                const META_KEY = '#14ljk_!\\]&0U<\'(';

                public function convert(string $file_path, string $output = null) {
                    $file = fopen($file_path, 'rb');
                    $header = fread($file, 8);
                    if ($header !== 'CTENFDAM') {
                        throw new \Exception("\"{$file_path}\" not a .ncm file.");
                    }
                    fseek($file, 2, SEEK_CUR);
                    $key_length = $this->unpack_I(fread($file, 4));
                    $key_data = '';
                    $key_raw_data = fread($file, $key_length);

                    for ($i = 0; $i < strlen($key_raw_data); $i++) {
                        $key_data .= chr(ord($key_raw_data[$i]) ^ 0x64);
                    }
                    $key_data = substr(trim(openssl_decrypt($key_data, 'AES-128-ECB', self::CORE_KEY, OPENSSL_RAW_DATA)), 17);
                    $key_length = strlen($key_data);

                    $key = array();
                    for ($i = 0; $i < strlen($key_data); $i++) {
                        $key[] = bin2hex($key_data[$i]);
                    }
                    $s = range(0x00, 0xff);
                    $j = 0;

                    foreach (range(0, 255) as $i) {
                        $j = ($j + $s[$i] + hexdec($key[$i % $key_length])) & 0xff;
                        list($s[$i], $s[$j]) = array($s[$j], $s[$i]);
                    }

                    $meta_length = $this->unpack_I(fread($file, 4));
                    if ($meta_length) {
                        $meta_raw_data = fread($file, $meta_length);
                        $meta_data = '';
                        for ($i = 0; $i < strlen($meta_raw_data); $i++) {
                            $meta_data .= chr(ord($meta_raw_data[$i]) ^ 0x63);
                        }
                        $meta_data = json_decode(substr(trim(openssl_decrypt(base64_decode(substr($meta_data, 22)), 'AES-128-ECB', self::META_KEY, OPENSSL_RAW_DATA)), 6), true);
                    } else {
                        $meta_data = array('format' => filesize($file_path) > 1024 ** 2 * 16 ? 'flac' : 'mp3');
                    }

                    fseek($file, 5, SEEK_CUR);


                    $image_space = $this->unpack_I(fread($file, 4));
                    $image_size = $this->unpack_I(fread($file, 4));
                    /* $image_data = */
                    $image_size ? fread($file, $image_size) : null;

                    fseek($file, $image_space - $image_size, SEEK_CUR);

                    $output = isset($output) ? $output : ($file_path . '.') . $meta_data['format'];

                    $data = fread($file, filesize($file_path));
                    fclose($file);

                    $stream = '';
                    foreach (range(0, 255) as $i) {
                        $stream .= chr($s[($s[$i] + $s[($i + $s[$i]) & 0xff]) & 0xff]);
                    }
                    $stream = substr(str_repeat($stream, (intdiv(strlen($data), 256) + 1)), 1, strlen($data));

                    $output_file = fopen($output, 'wb');
                    fwrite($output_file, $this->xor_string($data, $stream));
                    fflush($output_file);
                    fclose($output_file);
                }

                private function unpack_I($bytes): int {
                    return ord($bytes[0]) | ord($bytes[1]) << 8 | ord($bytes[2]) << 16 | ord($bytes[3]) << 24;
                }

                private function xor_string(string $str, string $key): string {
                    $outText = '';
                    for ($i = 0; $i < strlen($str);) {
                        for ($j = 0; ($j < strlen($key) && $i < strlen($str)); $j++, $i++) {
                            $outText .= $str{$i} ^ $key{$j};
                        }
                    }
                    return $outText;
                }
            };
            return $converter->convert($file);
        })->then(function () use ($logger, $index) {
            $logger->info("{{$index}} processed successfully.");
        })->catch(function (\Exception $e) use ($logger) {
            $logger->error(explode("\n", $e->getMessage())[0]);
        });
    }

    await($pool);

    $time = round(microtime(true) - $time, 2);
    $logger->info("done. Time consuming {$time}s");
    $logger->info('Convert ' . count($files) . ' music files in total.');
}