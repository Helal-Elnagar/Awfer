$(window).on("load", function () {
  //  Preloader

  $(".preloader-container").fadeOut();


  // App screens Swiper
  const appScreens = new Swiper(".app-screens .swiper", {
    loop: true,
     autoplay: true,
    allowTouchMove: true,
    speed: 800,
    centeredSlides: true,
    slideToClickedSlide: true,
    draggable: true,
    navigation: {
      nextEl: " .app-screens .swiper-button-next",
      prevEl: ".app-screens .swiper-button-prev",
    },
    pagination: {
      el: ".app-screens .swiper-pagination",
      clickable: true,
    },
    breakpoints: {
      0: {
        slidesPerView: 2,
        spaceBetween: 10,
      },
      767: {
        slidesPerView: 4,
        spaceBetween: 10,
      },
      992: {
        slidesPerView: 5,
        spaceBetween: 10,
      },
      1199: {
        slidesPerView: 6,
        spaceBetween: 10,
      },
    },
  });

  // Clients review swiper
  const clientsReview = new Swiper(".client-reviews .swiper", {
    loop: true,
     autoplay: true,
    allowTouchMove: true,
    speed: 800,
    draggable: true,
    navigation: {
      nextEl: " .client-reviews .swiper-button-next",
      prevEl: ".client-reviews .swiper-button-prev",
    },
    pagination: {
      el: ".client-reviews .swiper-pagination",
      clickable: true,
    },

    breakpoints: {
      0: {
        slidesPerView: 1,
        spaceBetween: 35,
      },
      767: {
        slidesPerView: 2,
        spaceBetween: 30,
      },
      992: {
        slidesPerView: 1.6,
        spaceBetween: 30,
      },
      1199: {
        slidesPerView: 2,
        spaceBetween: 30,
      },
    },
  });

  //  AOS animation
  AOS.init({
    once: true,
  });
});

$(document).ready(function () {
  // fire select2
  $(".myselect").select2();

  $(".myform select").select2({
    minimumResultsForSearch: -1,
  });

  // **************************************************************************************************

  if ($(window).width() < 768) {
    $(".download-parent .content").removeAttr("data-aos-delay");
    $(".download-parent .cta-mob").attr("data-aos-delay", "500");
    $(".features .mobile-hand-img").attr("data-aos-delay", "300");
  }

  if ($("html").attr("dir") == "ltr") {
    $(".download-parent .cta-mob").attr("data-aos", "fade-right");
  }

  // **************************************************************************************************

  // Common Questions

  $(".main-question").on("click", function () {
    $(this).children(".ques-answer").slideToggle(300);
    $(this).toggleClass("active").siblings().removeClass("active");
    $(".ques-answer").not($(this).children(".ques-answer")).slideUp(300);
  });

  // **************************************************************************************************

  // fixed header

  $(window).on("scroll", function () {

        
    if ($(window).scrollTop() > 1) {
      $("header").addClass("blur-header");
      if ($(window).width() > 992) {
        $("header").addClass("fixed-header");
      }
    }
    if ($(window).scrollTop() == 0) {
      $("header").removeClass("fixed-header");
      $("header").removeClass("blur-header");
    }
  });

  // open and close nav

  let openBtn = $(".openBtn");
  openBtn.on("click", function () {

   
    $(".navigation").slideToggle(400);
    $(".logo .logo-dark").toggleClass("show-logo-dark")
    $(".logo .logo-light").toggleClass("hide-logo-light")
    $(".header-links a").toggleClass("change-bg");
    $(".openBtn i").toggleClass("bars-white");
    $("body").toggleClass("overflow-hidden");
    $(this).children("i").toggleClass("fa-xmark");
    if ($(this).children("i").hasClass("fa-xmark")) {
      $(".big-menu li").addClass("opacity-100");
    } else {
      $(".big-menu li").removeClass("opacity-100");
    }
  });

  if ($(window).width() < 992) {
    $(".big-menu li a").on("click", function () {
      $(".navigation").slideUp(400);
      $(".openBtn i").removeClass("fa-xmark");
      $("body").removeClass("overflow-hidden");
    });
  }

  // **************************************************************************************************

  // to top button

  $(window).scroll(function () {
    if ($(window).scrollTop() > 150) {
      $(".toTop").addClass("showToTop");
    } else {
      $(".toTop").removeClass("showToTop");
    }
  });

  $(".toTop").click(function () {
    $(window).scrollTop(0);
  });

  // **************************************************************************************************
}); // End of document ready
