/**
 * OSM landing page: load the order after the shop shell is visible,
 * and keep the Elementor side cart closed until the shopper opens it.
 */
(function () {
	'use strict';

	var userOpenedCart = false;

	function cartWidgets() {
		return document.querySelectorAll('.elementor-widget-woocommerce-menu-cart');
	}

	function clearInlineHide(widget) {
		widget.querySelectorAll('.elementor-menu-cart__container, .elementor-menu-cart__main').forEach(function (el) {
			el.style.display = '';
		});
	}

	function setWidgetOpen(widget, open) {
		widget.classList.toggle('elementor-menu-cart--shown', open);
		clearInlineHide(widget);
		widget.querySelectorAll('.elementor-menu-cart__container, .elementor-menu-cart__main').forEach(function (el) {
			el.setAttribute('aria-hidden', open ? 'false' : 'true');
		});
		var toggle = widget.querySelector('.elementor-menu-cart__toggle_button');
		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
	}

	function closeAllCarts() {
		cartWidgets().forEach(function (widget) {
			setWidgetOpen(widget, false);
		});
		document.body.classList.remove('elementor-menu-cart--shown', 'ast-header-cart-drawer-open');
	}

	function elementorProOwnsCart() {
		return !!document.querySelector('script#elementor-pro-frontend-js, script#pro-elements-handlers-js');
	}

	function bindCartToggle() {
		// Always start closed. Fragment refresh and theme CSS must not win.
		closeAllCarts();

		if (window.jQuery) {
			window.jQuery(document.body).on('added_to_cart wc_fragments_refreshed wc_fragments_loaded', function () {
				if (!userOpenedCart) {
					closeAllCarts();
				}
			});
		}

		document.addEventListener('click', function (event) {
			var toggle = event.target.closest('.elementor-menu-cart__toggle_button');
			if (toggle) {
				var widget = toggle.closest('.elementor-widget-woocommerce-menu-cart');
				if (widget) {
					clearInlineHide(widget);
				}
				// Elementor Pro already toggles the drawer. Only record that the
				// shopper asked for it, so a fragment refresh does not snap it shut.
				if (elementorProOwnsCart()) {
					// Click is about to toggle. Record the state the shopper is asking for.
					if (widget) {
						userOpenedCart = !widget.classList.contains('elementor-menu-cart--shown');
					}
					return;
				}
				event.preventDefault();
				if (!widget) {
					return;
				}
				var open = !widget.classList.contains('elementor-menu-cart--shown');
				cartWidgets().forEach(function (other) {
					if (other !== widget) {
						setWidgetOpen(other, false);
					}
				});
				setWidgetOpen(widget, open);
				userOpenedCart = open;
				document.body.classList.toggle('elementor-menu-cart--shown', open);
				return;
			}

			var closer = event.target.closest('.elementor-menu-cart__close-button, .astra-cart-drawer-close');
			var container = event.target.closest('.elementor-menu-cart__container');
			var onOverlay = container && !event.target.closest('.elementor-menu-cart__main');
			if (closer || onOverlay) {
				userOpenedCart = false;
				closeAllCarts();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				userOpenedCart = false;
				closeAllCarts();
			}
		});
	}

	function showFetchError(results, message) {
		results.removeAttribute('data-osm-loading');
		results.innerHTML = '<div class="osm-badge-landing__error" role="alert"><p></p></div>';
		var p = results.querySelector('p');
		if (p) {
			p.textContent = message;
		}
	}

	function refreshCartFragments() {
		if (!window.jQuery) {
			return;
		}
		window.jQuery(document.body).trigger('wc_fragment_refresh');
	}

	function loadOrder() {
		var results = document.getElementById('osm-badge-landing-results');
		if (!results || results.getAttribute('data-osm-loading') !== '1') {
			return;
		}
		var cfg = window.osmBadgeLanding || {};
		var params;
		try {
			params = new URL(window.location.href).searchParams;
		} catch (err) {
			showFetchError(results, cfg.i18nError || 'We could not load this badge order.');
			return;
		}
		var orderUrl = params.get('order_url') || '';
		if (!orderUrl) {
			return;
		}

		var body = new URLSearchParams();
		body.set('osm_badge_fetch', '1');
		body.set('order_url', orderUrl);
		body.set('_wpnonce', cfg.nonce || '');

		var endpoint = new URL(window.location.href);
		endpoint.searchParams.delete('osm_sync');

		var controller = typeof AbortController === 'function' ? new AbortController() : null;
		var timer = controller
			? window.setTimeout(function () {
				controller.abort();
			}, 40000)
			: null;

		fetch(endpoint.toString(), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				Accept: 'application/json'
			},
			body: body.toString(),
			signal: controller ? controller.signal : undefined
		})
			.then(function (response) {
				return response.json().then(function (payload) {
					return { ok: response.ok, payload: payload };
				});
			})
			.then(function (result) {
				var payload = result.payload || {};
				var data = payload.data || {};
				if (!result.ok || !payload.success || typeof data.html !== 'string') {
					var message = (data && data.message) || (payload.data && payload.data.message) || cfg.i18nError;
					showFetchError(results, message || cfg.i18nError);
					return;
				}
				results.removeAttribute('data-osm-loading');
				results.innerHTML = data.html;
				if (!userOpenedCart) {
					closeAllCarts();
				}
				var alertEl = results.querySelector('[role="alert"]');
				if (alertEl) {
					if (!alertEl.hasAttribute('tabindex')) {
						alertEl.setAttribute('tabindex', '-1');
					}
					alertEl.focus();
				}
				refreshCartFragments();
			})
			.catch(function () {
				showFetchError(results, cfg.i18nError || 'We could not load this badge order.');
			})
			.then(function () {
				if (timer) {
					window.clearTimeout(timer);
				}
			});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			bindCartToggle();
			loadOrder();
		});
	} else {
		bindCartToggle();
		loadOrder();
	}
})();
