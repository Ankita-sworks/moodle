	var url_string = window.location.href; //window.location.href
	var url = new URL(url_string);
	var c = url.searchParams.get("page");

	if (c==14)
	{
	console.log(c);
	$('.formulation').addClass('myClass-14');
	}
    $( document ).ready(function(){

    $( document ).on( "change", ".input-numeric", function(){
          var parents = $( this ).parents( ".input-numeric-container" );
        var input = parents.find( ".input-numeric");
        var nonKeys = parents.find( ".key-del, .key-clear");
        var inputValue = input.val();
                
        if( inputValue == "" ){
            nonKeys.prop( "disabled", true );
        } else {
            nonKeys.prop( "disabled", false );
        }
        
    });

  
    $( document ).on( "click focus", ".input-numeric", function(){
    
          var parents = $( this ).parents( ".input-numeric-container" );
        var data = parents.attr( "data-numeric" );
    
        if( data ){
            if( data == "hidden" ){
               parents.find( ".table-numeric" ).show();
            }
        }
        
    });
  
    
    //key numeric
    $( document ).on( "click", ".key", function(){
      
        var parents = $( this ).parents( ".input-numeric-container" );
        var number = $( this ).attr( "data-key" );
        var input = parents.find( ".input-numeric");
        var inputValue = input.val();
        var nonKeys = parents.find( ".key-del, .key-clear");
         
         nonKeys.prop( "disabled", false );
        input.val( inputValue + number ).change();
        $('.d-inline').val( inputValue + number ).change();
        
    });
    
    
    //delete
    $( document ).on( "click", ".key-del", function(){
    
          var parents = $( this ).parents( ".input-numeric-container" );
        var input = parents.find( ".input-numeric");
        var inputValue = input.val();
        
        input.val( inputValue.slice(0, -1) ).change();
       $('.d-inline').val( inputValue.slice(0, -1) ).change();
        
    });
    
    
    //clear
    $( document ).on( "click", ".key-clear", function(){
    
          var parents = $( this ).parents( ".input-numeric-container" );
        var input = parents.find( ".input-numeric");
        
        input.val( "" ).change();
         $('.d-inline').val( "" ).change();
        
    });

});
$(document).ready(function () {
  $('select').removeClass('select custom-select custom-select place1');

    
  $("input[value='Finish attempt ...']").val("Submit");
  $("input[name='next']").val(">");
  $("input[name='previous']").val("<")
  $(".endtestlink").text('Submit');
  $(".endtestlink").addClass('btn btn-primary');
  $(".qnbutton").addClass("show");



 $(".multipages").append('<div id = "left-arrow">'
                + '<i class="fas fa-angle-left"></i>');
 $(".multipages").append('<div id = "newElement">'
                + 'Marks: 02 <span class="fa fa-star 02star" id="starId"></span>');
});
$(".multipages").append('<div id = "right-arrow">'
                + '<i class="fas fa-angle-right"></i>');
setTimeout(function(){
    $('.bg-green').is( function() {
        $('.endtestlink').addClass('bg-green');
        $('.thispage').addClass('bg-green');
        $('#starId').addClass('bg-green');
    })
},80)
setTimeout(function(){
    $('.bg-neonCarrot').is( function() {
        $('.endtestlink').addClass('bg-neonCarrot');
        $('.thispage').addClass('bg-neonCarrot');
        $('#starId').addClass('bg-neonCarrot');
    })
},80)

setTimeout(function(){
    $('.no-1').is( function() {
        $('.endtestlink').addClass('blue-color');
        $('.thispage').addClass('blue-color');
        $('#starId').addClass('blue-color');
    })
},80)

var arr1 = [];
var numItems = $('.qnbutton').length
// console.log(numItems);
var div=10;
$( '.qnbutton').each(function() {
var correct = parseInt($(this).attr('data-quiz-page'))+1; 
if(correct%div)
{
    $(this).addClass('Less'+div);
}
else if(correct%div==0)
{
    $(this).addClass('Less'+div);
    div+=correct;
}
  
});
// setTimeout(function(){
// $('.Less20').css('display','none');
// $("#left-arrow").click(function(){
// 	$('.Less10').css('display','block');
// 	$('.Less20').css('display','none');
// });
// },80);
   

// $("#right-arrow").click(function(e){
//   $(location).attr('href');
//      var bn_pathname = window.location.href;
//      var en_pathname = bn_pathname.replace("cmid=2058", "cmid=2058&page=10"); 
//      window.location.replace(en_pathname);
// 	$('.Less10').css('display','none');
// 	$('.Less20').css('display','block');
// });
