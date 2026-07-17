(function() {
	'use strict';

	var tinyslider = function() {
		var el = document.querySelectorAll('.testimonial-slider');

		if (el.length > 0) {
			var slider = tns({
				container: '.testimonial-slider',
				items: 1,
				axis: "horizontal",
				controlsContainer: "#testimonial-nav",
				swipeAngle: false,
				speed: 700,
				nav: true,
				controls: true,
				autoplay: true,
				autoplayHoverPause: true,
				autoplayTimeout: 3500,
				autoplayButtonOutput: false
			});
		}
	};
	tinyslider();

	


	var sitePlusMinus = function() {

		var value,
    		quantity = document.getElementsByClassName('quantity-container');

		function createBindings(quantityContainer) {
	      var quantityAmount = quantityContainer.getElementsByClassName('quantity-amount')[0];
	      var increase = quantityContainer.getElementsByClassName('increase')[0];
	      var decrease = quantityContainer.getElementsByClassName('decrease')[0];
	      increase.addEventListener('click', function (e) { increaseValue(e, quantityAmount); });
	      decrease.addEventListener('click', function (e) { decreaseValue(e, quantityAmount); });
	    }

	    function init() {
	        for (var i = 0; i < quantity.length; i++ ) {
						createBindings(quantity[i]);
	        }
	    };

	    function increaseValue(event, quantityAmount) {
	        value = parseInt(quantityAmount.value, 10);

	        console.log(quantityAmount, quantityAmount.value);

	        value = isNaN(value) ? 0 : value;
	        value++;
	        quantityAmount.value = value;
	    }

	    function decreaseValue(event, quantityAmount) {
	        value = parseInt(quantityAmount.value, 10);

	        value = isNaN(value) ? 0 : value;
	        if (value > 0) value--;

	        quantityAmount.value = value;
	    }
	    
	    init();
		
	};
	sitePlusMinus();


})()
(function () {
	"use strict";
	document.querySelectorAll(".navbar-brand").forEach(function (brand) {
		brand.innerHTML = "IAN<span>PROJECT</span>";
	});
	var labels = {
		"shop.html": "Products",
		"services.html": "Custom Solutions",
		"about.html": "Factory",
		"blog.html": "Insights",
		"contact.html": "Contact"
	};
	Object.keys(labels).forEach(function (href) {
		document.querySelectorAll('.custom-navbar-nav a[href="' + href + '"]').forEach(function (link) {
			link.textContent = labels[href];
		});
	});
	var nav = document.querySelector(".custom-navbar-nav");
	if (nav && !nav.querySelector('a[href="project.html"]')) {
		var factoryItem = nav.querySelector('a[href="about.html"]');
		var projectItem = document.createElement("li");
		projectItem.innerHTML = '<a class="nav-link" href="project.html">Projects</a>';
		if (factoryItem && factoryItem.parentNode) {
			factoryItem.parentNode.insertAdjacentElement("afterend", projectItem);
		}
	}
	document.querySelectorAll(".footer-logo").forEach(function (brand) {
		brand.innerHTML = "IAN<span>PROJECT</span>";
		brand.setAttribute("href", "index.html");
	});
})();
