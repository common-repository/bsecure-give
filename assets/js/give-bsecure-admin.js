


// Hnadle survey popup when deactivating plugin //
jQuery(document).on("click", "#deactivate-give-bsecure-payment-gateway", function(e){
    e.preventDefault();
	var deactivatePlugin = jQuery(this).attr('href');
	jQuery.post(ajaxurl,{"action":"give_bsecure_deactivation_popup"},function(res){ 
			},"json").done(function(res) {		
		var surveyHhtml = res.html;
		if(jQuery("#give-bsecure-survey-popup").length > 0){
			jQuery("#give-bsecure-survey-popup").remove();
		}
		jQuery("body").append('<div id="give-bsecure-survey-popup" style="display:none;">'+ surveyHhtml +'</div><a href="#TB_inline?&width=600&height=550&inlineId=bsecure-survey-popup&class=abc" class="thickbox bsecure-survey" style="display:none;" title="bSecure Deactivation Quick Feedback"></a>');
		jQuery(".bsecure-survey").trigger("click");
		setTimeout(function(){
		    jQuery(".bsecure-skip-deactivate-survey").attr('href',deactivatePlugin);
		    jQuery("#TB_window").addClass('thickbox-wrapper');
		},200)
	});
    
});

jQuery(document).on("change","input[name=give_bsecure-survey-radios]", function(){

	jQuery(".bsecure-survey-extra-field").hide();
	jQuery(".widefat").attr("disabled","disabled");
	var val = jQuery(this).val();
    if(val !== 1 && val !== 4){
        jQuery(".reason-box"+val).show();
        jQuery(".reason-box"+val).find("textarea").removeAttr("disabled");
    } 
        
});


jQuery(document).on('submit', 'form.give_bsecure-deactivation-survey-form', function(e){
	e.preventDefault();
	var frmData = jQuery(this).serializeArray();
	var spinner = jQuery(this).find(".spinner");
	spinner.css("visibility","visible");
	jQuery.post(ajaxurl, frmData ,function(res){ 
			},"json").done(function(res) {

		if(res.success){

			jQuery(".ajax-msg").html('<div class="notice notice-success is-dismissible"><p>Your feedback submitted successfully.</p></div>');

			setTimeout(function(){
				document.location.href = jQuery("#deactivate-give-bsecure-payment-gateway").attr('href');
			},1000);

		}else{

			jQuery(".ajax-msg").html('<div class="notice notice-error is-dismissible"><p>'+ res.data +'</p></div>');
		}		

	})
	.fail(function(res) {

        jQuery(".ajax-msg").html('<div class="notice notice-error is-dismissible"><p>An error occurred while sending your request. Please try again.</p></div>');
    })
	.always(function(res) {

		spinner.css("visibility","hidden");		
			
	});
});