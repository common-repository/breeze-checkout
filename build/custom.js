(function ($) {
	const wc_set_local_atoms = function () {
		var localAtomsValue = localStorage.getItem("local-atoms");
		console.log("localAtomsValue: ", localAtomsValue);
		var data = {
			action: "set_local_atoms",
			localAtomsValue: localAtomsValue,
			nonce: window.breezeAjax?.set_local_atoms_nonce
				? window.breezeAjax?.set_local_atoms_nonce
				: null,
		};
		if (window.breezeAjax?.ajaxurl) {
			jQuery
				.post(window.breezeAjax.ajaxurl, data)
				.done(function (response) {
					console.log("success");
					return response;
				})
				.fail(function (response) {
					console.log("fail");
				});
		}
	};

	const wc_add_support_utm_params = function () {
		function cookieParser(cookieString) {
			if (cookieString === "") return {};
			let pairs = cookieString.split(";");
			let splittedPairs = pairs.map((cookie) => cookie.split("="));
			const cookieObj = splittedPairs.reduce(function (obj, cookie) {
				obj[decodeURIComponent(cookie[0].trim())] = decodeURIComponent(
					cookie[1].trim()
				);
				return obj;
			}, {});
			return cookieObj;
		}

		let cookieDataFromSite = document.cookie;
		let cookieObj = cookieParser(cookieDataFromSite);
		let cookieCheckoutUrl = cookieObj["sbjs_session"].toString();
		let wc_order_attribution_source_type;
		let wc_order_attribution_utm_source;
		let wc_order_attribution_device_type;
		let wc_order_attribution_session_pages;
		let wc_order_attribution_origin;
		let wc_order_attribution_utm_medium;
		let wc_order_attribution_utm_campaign;

		function processString(str) {
			if (str === null || str === undefined) {
				return "";
			} else {
				return str.toString().toLowerCase().trim();
			}
		}

		function stringContains(mainString, subString) {
			mainString = mainString.toLowerCase();
			subString = subString.toLowerCase();

			return mainString.includes(subString);
		}

		function constructOriginFromSourceAndMedium(source, medium) {
			let source_one = "";
			let source_second = "";

			if (source.length == 0 && medium.length == 0) {
				return "Unknown";
			}

			if (stringContains(medium, "fbads") || stringContains(medium, "cpc")) {
				source_one = "Source: ";
			} else {
				source_one = medium + ": ";
			}

			source_second = source;

			return source_one + source_second;
		}

		function constructSourceTypeFromCookieOrSourceAndMedium(source, medium) {
			let sourceType = "";

			let trafficInfo = extractTrafficOrigin();

			if (
				trafficInfo.hasOwnProperty("typ") &&
				trafficInfo["typ"] !== null &&
				trafficInfo["typ"] !== "(none)"
			) {
				sourceType = trafficInfo["typ"];
				return sourceType;
			}

			if (source.length == 0 && medium.length == 0) {
				return "Unknown";
			}

			if (stringContains(source, "direct")) {
				sourceType = "typein";
			} else if (
				stringContains(medium, "fbads") ||
				stringContains(medium, "cpc")
			) {
				sourceType = "utm";
			} else {
				sourceType = medium;
			}

			return sourceType;
		}

		function parseUserAgent() {
			const userAgent = navigator.userAgent.toLowerCase();
			const userAgentFromCookie = processString(cookieObj["sbjs_udata"]);

			if (userAgent.length !== 0) {
				if (
					userAgent.match(
						/android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i
					)
				) {
					return "Mobile";
				} else {
					return "Desktop";
				}
			} else {
				if (
					userAgentFromCookie.match(
						/android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i
					)
				) {
					return "Mobile";
				} else {
					return "Desktop";
				}
			}
		}

		function extractTrafficOrigin() {
			let encodedString = processString(cookieObj["sbjs_first"]);

			const decodedString = decodeURIComponent(encodedString);
			const pairs = decodedString.split("|||");
			const trafficInfo = {};

			pairs.forEach((pair) => {
				const [key, value] = pair.split("=");
				const cleanedValue = value ? value.replace(/\(|\)/g, "") : undefined;
				trafficInfo[key] = cleanedValue;
			});

			return trafficInfo;
		}

		function checkExistsAndNotNull(key, trafficOriginInfo) {
			if (
				trafficOriginInfo.hasOwnProperty(key) &&
				trafficOriginInfo[key] !== null &&
				trafficOriginInfo[key] !== "(none)"
			) {
				return trafficOriginInfo[key];
			} else {
				return "";
			}
		}

		if (cookieCheckoutUrl.includes("wcf_ac_token")) {
			wc_order_attribution_source_type = "utm";
			wc_order_attribution_utm_source = "abandonedcart";
		} else {
			if (cookieObj["sbjs_first"]) {
				let trafficOriginInfo = extractTrafficOrigin();

				wc_order_attribution_source_type = checkExistsAndNotNull(
					"typ",
					trafficOriginInfo
				);
				wc_order_attribution_utm_source = checkExistsAndNotNull(
					"src",
					trafficOriginInfo
				);
				wc_order_attribution_utm_medium = checkExistsAndNotNull(
					"mdm",
					trafficOriginInfo
				);
				wc_order_attribution_origin = constructOriginFromSourceAndMedium(
					wc_order_attribution_utm_source,
					wc_order_attribution_utm_medium
				);
			} else if (cookieObj["utm_source"]) {
				wc_order_attribution_utm_source = processString(
					cookieObj["utm_source"]
				);
				wc_order_attribution_utm_medium = processString(
					cookieObj["utm_medium"]
				);
				wc_order_attribution_origin = constructOriginFromSourceAndMedium(
					wc_order_attribution_utm_source,
					wc_order_attribution_utm_medium
				);
				wc_order_attribution_source_type =
					constructSourceTypeFromCookieOrSourceAndMedium(
						wc_order_attribution_utm_source,
						wc_order_attribution_utm_medium
					);
			} else if (typeof cookieObj["wpmReferrer"] !== "undefined") {
				let arrOfRef = cookieObj["wpmReferrer"].toString().split(".");

				wc_order_attribution_utm_source =
					arrOfRef[0] === "www" ? arrOfRef[1] : cookieObj["wpmReferrer"];
				wc_order_attribution_utm_medium = processString(
					cookieObj["utm_medium"]
				);
				wc_order_attribution_origin = constructOriginFromSourceAndMedium(
					wc_order_attribution_utm_source,
					wc_order_attribution_utm_medium
				);
				wc_order_attribution_source_type =
					constructSourceTypeFromCookieOrSourceAndMedium(
						wc_order_attribution_utm_source,
						wc_order_attribution_utm_medium
					);
			} else {
				wc_order_attribution_origin = "Unknown";
				wc_order_attribution_source_type = "";
				wc_order_attribution_utm_source = "";
				wc_order_attribution_utm_medium = processString(
					cookieObj["utm_medium"]
				);
			}
		}

		wc_order_attribution_utm_campaign = processString(
			cookieObj["utm_campaign"]
		);
		wc_order_attribution_device_type = parseUserAgent();
		wc_order_attribution_session_pages = processString(
			cookieObj["sbjs_session"]
		)
			.toString()
			.split("|||")[0]
			.split("=")[1];

		const createOrderPayload = {
			action: "get_utm_params",
			source_type: wc_order_attribution_source_type,
			utm_source: wc_order_attribution_utm_source,
			device_type: wc_order_attribution_device_type,
			session_pages: wc_order_attribution_session_pages,
			utm_medium: wc_order_attribution_utm_medium,
			utm_campaign: wc_order_attribution_utm_campaign,
			utm_origin: wc_order_attribution_origin,
			nonce: window.breezeAjax?.get_utm_params_nonce
				? window.breezeAjax?.get_utm_params_nonce
				: null,
		};

		if (window.breezeAjax?.ajaxurl) {
			jQuery
				.post(window.breezeAjax.ajaxurl, createOrderPayload)
				.done(function (response) {
					console.log("success");
					return response;
				})
				.fail(function (response) {
					console.log("failure");
				});
		}
	};

	const wc_send_purchase_event_for_ga = function () {
		// `breeze-purchase` event listener
		window.addEventListener("breeze-purchase", (event) => {
			const eventData = event.detail;
			breezePurchase(eventData);
		});

		// This function will be invoked by breeze after purchase
		function breezePurchase(payload) {
			try {
				merchantPurchaseCustomCode(payload.data);
			} catch (e) {
				console.error("Error in triggering merchants purchase script : ", e);
			}
		}

		// merchant script for `purchase` will be pasted here
		function merchantPurchaseCustomCode(params) {
			// GOOGLE ADS PURCHASE EVENT CODE START
			console.error(">>>> sent purchase >>>");
			window.dataLayer = window.dataLayer || [];
			function gtag() {
				dataLayer.push(arguments);
			}
			gtag("js", new Date());
			gtag("config", "AW-<CHANGE>");
			window.gtag("event", "purchase", {
				send_to: ["AW-<CHANGE>/LbFJCN...P4YEOSFgpgo"],
				value: params.totalPrice,
				currency: "INR",
				transaction_id: params.shopOrderId,
			});
			// GOOGLE ADS PURCHASE EVENT CODE END
		}
	};

	$(function () {
		wc_set_local_atoms();
		wc_add_support_utm_params();
		wc_send_purchase_event_for_ga();
	});
})(jQuery);
