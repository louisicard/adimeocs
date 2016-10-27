(function($){

  $(document).ready(function(){
    bindCrawlerForm();
  });

  function bindCrawlerForm(){
    $('select#form_callback').unbind('change');
    $('select#form_callback').change(function(){
      if(typeof __adimeoCSCallbackFormAjaxUrl !== 'undefined'){

        var values = {};
        $('#crawler-settings-form').find('input,select,textarea').each(function(){
          values[$(this).attr('id')] = $(this).val();
        });

        $('#crawler-settings-form').html('Loading. Please wait...');

        $.ajax({
          url: __adimeoCSCallbackFormAjaxUrl + '?callback=' + encodeURIComponent($(this).val())
        }).success(function(html){
          var toInsert = $(html).find('#crawler-settings-form');
          $('#crawler-settings-form').html(toInsert.html());
          $('#crawler-settings-form').find('input,select,textarea').each(function(){
            if(typeof values[$(this).attr('id')] !== 'undefined'){
              $(this).val(values[$(this).attr('id')]);
            }
          });
          bindCrawlerForm();
        });
      }
    });
  }

})(jQuery);