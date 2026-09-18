<?php
// Native normalisation and adapter projection; no production bootstrap or browser execution.
require __DIR__.'/test-passage-media.php';
$display->setRelation('passage',null);$display->setRelation('langs',collect([]));
$attempt->end_time=null;
$formula='<math><mfrac><mn>1</mn><mn>2</mn></mfrac></math>';
$generated='<mjx-container class="MathJax"><svg><path d="M0 0"></path></svg><mjx-assistive-mml>'.$formula.'</mjx-assistive-mml></mjx-container>';
$chtml='<mjx-container><mjx-math><mjx-mi>x</mjx-mi></mjx-math><mjx-assistive-mml>'.$formula.'</mjx-assistive-mml></mjx-container>';
$render=fn()=>$projection->fromNativeView($native,20,$student->id)['questions'][0];
foreach([$formula,$generated,$chtml,'<span class="mathjax-mathml">'.$formula.'</span>'] as $sample){
 $display->question='Before '.$sample.' after';
 $html=$render()['content']['question'];
 check(str_contains($html,'Before')&&str_contains($html,'after')&&str_contains($html,'\frac{1}{2}'),'Native MathML and generated MathJax convert without losing adjacent wording');
 check(!preg_match('/<(?:svg|mjx-|math)/i',$html),'Generated visual wrappers never escape into student markup');
}
$display->question='Plain question';$display->option1=$generated;$display->hint=$chtml;
$content=$render()['content'];
check(str_contains($content['option1'],'\frac{1}{2}')&&str_contains($content['hint'],'\frac{1}{2}'),'Options and hints share native formula conversion');
$display->option1='';$display->hint='';
foreach([
 '<svg><path d="M0 0"></path></svg>',
 '<mjx-container><svg></svg></mjx-container>',
 '<mjx-container><svg></svg><mjx-assistive-mml><math></math></mjx-assistive-mml></mjx-container>',
 '<mjx-container><svg></svg><mjx-assistive-mml>'.$formula.$formula.'</mjx-assistive-mml></mjx-container>',
 '<mjx-container><svg></svg><div>'.$formula.'</div></mjx-container>',
 '<mjx-container><svg></svg><svg></svg><mjx-assistive-mml>'.$formula.'</mjx-assistive-mml></mjx-container>',
 '<mjx-math>x</mjx-math>',
 '<mjx-container>'.str_repeat('<mjx-row>',65).$formula.str_repeat('</mjx-row>',65).'</mjx-container>',
 '<math><menclose notation="circle"><mi>x</mi></menclose></math>',
 '<math-field>x</math-field>',
] as $invalid){
 $display->question=$invalid;
 rejectAnswer(fn()=>$render(),'Unrecoverable formula or vector content must stop delivery');
}
$display->question='Plain question';$display->option6='<math><menclose notation="circle"><mi>x</mi></menclose></math>';
rejectAnswer(fn()=>$render(),'Needs-review formula in an option is not silently passed through');
$display->option6='';$display->setRelation('passage',$passage);
$otherWording->passage='Passage '.$generated.' <img src="'.$otherImage.'">';$otherWording->save();
$html=$render()['passage']['content'];
check(str_contains($html,'\frac{1}{2}')&&str_contains($html,'t4l-media:'.hash('sha256',$otherImage)),'Passages combine generated native formulas with protected diagrams');
$otherWording->passage='<math><menclose notation="circle"><mi>x</mi></menclose></math>';$otherWording->save();
rejectAnswer(fn()=>$render(),'Needs-review passage formula stops delivery');
echo "Native student formulas: MathML, recoverable MathJax SVG/CHTML, options, hints, passages and fail-closed unsupported content passed.\n";
