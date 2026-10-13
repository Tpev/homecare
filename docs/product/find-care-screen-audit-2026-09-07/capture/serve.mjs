import {spawn} from 'node:child_process';
import {env,root} from './environment.mjs';
const child=spawn('php',['-d','zend_extension=opcache','-d','opcache.enable_cli=1','-d','opcache.memory_consumption=192','-S','127.0.0.1:8027',root+'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{cwd:root+'/public',env,stdio:'inherit',windowsHide:true});
process.on('SIGINT',()=>child.kill());
child.on('exit',code=>process.exit(code??0));
