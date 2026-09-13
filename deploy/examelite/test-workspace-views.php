<?php
require $argv[1];
$blade=new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(),sys_get_temp_dir());
foreach(['launch','library','navigation'] as $name) {
 $compiled=$blade->compileString(file_get_contents(__DIR__.'/'.$name.'.blade.php'));
 $temp=tempnam(sys_get_temp_dir(),'t4l-view-');file_put_contents($temp,$compiled);
 try {exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp),$output,$code);if($code!==0)throw new RuntimeException('Invalid Blade output: '.$name);}
 finally {unlink($temp);}
}
echo "Workspace Blade templates compile and pass PHP syntax checks.\n";
