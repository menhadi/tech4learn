/*
Template Name: Examframe - 
Author: ExamFrame
Version: 4.3.0
Website: https://ExamFrame.com/
Contact: ExamFrame@gmail.com
File: Common Plugins Js File
*/

//Common plugins
if (
    document.querySelectorAll("[toast-list]") ||
    document.querySelectorAll("[data-choices]") ||
    document.querySelectorAll("[data-provider]")
) {
    document.writeln(
        "<script type='text/javascript' src='https://cdn.jsdelivr.net/npm/toastify-js'></script>"
    );
    document.writeln(
        "<script type='text/javascript' src='build/libs/choices.js/public/assets/scripts/choices.min.js'></script>"
    );
    document.writeln(
        "<script type='text/javascript' src='build/libs/flatpickr/flatpickr.min.js'></script>"
    );
}
