{{-- 
    File: resources/views/components/head/tinymce-config.blade.php 
--}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.9/tinymce.min.js" referrerpolicy="origin"></script>

<script>
  document.addEventListener("DOMContentLoaded", function() {
    
    // Function to initialize editor
    function initMyEditor() {
        // Ye un sabhi textareas ko target karega jinki ID 'description-field-' se shuru hoti hai
        // Example: description-field-en, description-field-hi
        tinymce.init({
            selector: 'textarea[id^="description-field-"]', 
            height: 400,
            menubar: false,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount'
            ],
            toolbar: 'undo redo | formatselect | ' +
                'bold italic backcolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help',
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
        });
    }

    // Modal open hone par editor load karein (Kyunki editor hidden tabs me hota hai)
    var myModal = document.getElementById('showModal');
    if(myModal){
        myModal.addEventListener('shown.bs.modal', function () {
            // Agar pehle se koi instance hai to remove karein taaki duplicate na ho
            tinymce.remove();
            initMyEditor();
        });
    } else {
        // Agar modal nahi hai (normal page), to direct load karein
        initMyEditor();
    }
  });
</script>