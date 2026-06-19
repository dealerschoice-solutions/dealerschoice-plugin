jQuery(document).ready(function($) {
    if($('.boat-gallery').length){
        $('.boat-gallery').each(function(){
            var slider = $(this).attr('id');
            $('#'+slider).slick({
                slidesToScroll: 1,
                slidesToShow: 1,
                infinite: false,
                appendArrows: '#' + slider + '-buttons',
                prevArrow: '<button class="slick-prev slick-arrow" type="button"><span class="dc-screen-reader-text">Previous</span><i class="fa-light fa-arrow-left"></i></button>',
                nextArrow: '<button class="slick-next slick-arrow" type="button"><span class="dc-screen-reader-text">Next</span><i class="fa-light fa-arrow-right"></i></button>',
            });
        });
    }

    if($('.gallery-modal').length){
        $('.gallery-modal').modaal({
            type: 'image'
        });
    }
});