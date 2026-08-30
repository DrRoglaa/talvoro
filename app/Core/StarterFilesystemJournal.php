<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class StarterFilesystemJournal
{
    private string $path;
    /** @var list<string> */
    private array $created=[];

    public function __construct(string $token)
    {
        if(preg_match('/^[a-f0-9]{32}$/D',$token)!==1) throw new RuntimeException('Starter filesystem journal token is invalid.');
        $root=base_path('storage/theme-imports');
        if(!is_dir($root) && !mkdir($root,0750,true) && !is_dir($root)) throw new RuntimeException('Starter journal directory is not writable.');
        $this->path=$root.'/starter-'.$token.'.json';
        $this->flush();
    }

    public function record(string $publicPath): void
    {
        $safe=SiteAssets::managedUploadPath($publicPath);
        if($safe==='') throw new RuntimeException('Starter journal accepts Talvoro site-upload paths only.');
        if(!in_array($safe,$this->created,true)){$this->created[]=$safe;$this->flush();}
    }

    /** @param list<string> $paths */
    public function recordMany(array $paths): void { foreach($paths as $path) if(is_string($path)&&$path!=='') $this->record($path); }

    public function rollback(): void
    {
        foreach(array_reverse($this->created) as $path) SiteAssets::remove($path);
        $this->complete();
    }

    public function complete(): void { if(is_file($this->path)) @unlink($this->path); $this->created=[]; }

    private function flush(): void
    {
        $json=json_encode(['created'=>$this->created],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $tmp=$this->path.'.tmp';
        if(file_put_contents($tmp,$json,LOCK_EX)===false || !@rename($tmp,$this->path)) throw new RuntimeException('Could not persist starter filesystem journal.');
        @chmod($this->path,0640);
    }
}
