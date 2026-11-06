// Payfast redirection

$(document).bind('em_booking_gateway_add_payfast', function (event, response){
  // called by EM if return JSON contains gateway key, notifications messages are shown by now.
  if(response.result){
    var ppForm = $('<form action="' + response.payfast_url + '" method="post" id="em-payfast-redirect-form"></form>');
    $.each(response.payfast_vars, function (index, value){
      ppForm.append('<input type="hidden" name="' + index + '" value="' + value + '" />');
    });
    ppForm.append('<input id="em-payfast-submit" type="submit" style="display:none" />');
    ppForm.appendTo('body').trigger('submit');
  }
});

