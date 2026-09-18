<?php
namespace App\Support {
    // The native translator runs; this boundary can never contact an AI provider.
    class AiProvider {
        public static array $owners=[];
        public static array $payloads=[];
        public static string $mode='valid';
        public static $duringGeneration=null;
        public static function firstAvailable($configuration,...$args){
            self::$owners[]=(int)$configuration?->organization_id;
            return ['provider'=>'synthetic','stored_name'=>'SYNTHETIC'];
        }
        public static function generateText($provider,$prompt,...$args){
            if(self::$mode==='failure')throw new \RuntimeException('Synthetic provider failure');
            if(self::$mode==='invalid')return 'not a translation JSON response';
            $payload=json_decode(substr($prompt,strrpos($prompt,"\n\n")+2),true,512,JSON_THROW_ON_ERROR);
            self::$payloads[]=$payload;
            if(self::$duringGeneration){$hook=self::$duringGeneration;self::$duringGeneration=null;$hook();}
            $translate=function(array $fields):array{
                foreach($fields as $key=>$value)if($key!=='id'&&is_string($value)&&trim($value)!=='')$fields[$key]='[synthetic target] '.$value;
                return $fields;
            };
            $result=['exam'=>$payload['exam']===null?null:$translate($payload['exam']),
                'questions'=>array_map($translate,$payload['questions'])];
            if(self::$mode==='omit')unset($result['questions'][1]['question']);
            return json_encode($result,JSON_THROW_ON_ERROR);
        }
    }
}
