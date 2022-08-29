<?php


namespace Erg\SsoDepartmentPosition\App\Console\Utils;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class Logger
{

    const SAVE_DAYS = 7;
    const DISC = 'log';

    protected ?string $path = null;

    private bool $oldIsDeleted = false;
    private string $file;


    public function __construct(Command $command, string $file)
    {
        $this->command = $command;
        $this->file = $file;
        $this->setPath($file);
    }


    public function setPath(string $file): string
    {
        $this->path = 'command/' . $file . date('_Y_m_d_H_i_s'). '.log';
        return $this->path;
    }

    public function log(string $text, bool $success = true): bool
    {
        $this->deleteOld();
        $logText = $success ? $text : 'Error! '.$text;
        $logText = date('Y-m-d H:i:s ') . $logText;

        Storage::disk(self::DISC)->append($this->path, $logText);

        return true;
    }

    private function deleteOld(): int
    {
        if ($this->oldIsDeleted) {
            return 0;
        }
        $this->oldIsDeleted = true;
        $path = Config::get('filesystems.disks.' . self::DISC . '.root') . '/command/';

        if (!file_exists($path)) {
            return 0;
        }
        $files = array_diff(scandir($path), ['.', '..']);

        $deleted  = 0;
        $now = time();

        foreach ($files as $file) {
            if (preg_match("/^" . $this->file . ".*\.log$/", $file)) {
                $filePath = $path . '/' . $file;
                $fileTime = fileatime($filePath);
                $fileIsOld = ($now - $fileTime) > 3600 * 24 * self::SAVE_DAYS;
                if ($fileIsOld && unlink($filePath)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
