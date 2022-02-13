const basicMethods = {
	contact: function(data) {
		if(data.success) document.getElementById("contact-form").reset();
		this.parseMsgs(data, "contact");
		if(data.csrf) this.resetToken(data);
	},

	subscribe: async function(data) {
		if(data.success) document.getElementById("subscribe-form").reset();
		this.parseMsgs(data, this.getContentId(["content", "contact"]));
		if(data.csrf) this.resetToken(data);
	},

	resetToken: function(data) {
		if(data.csrf && data.csrf.tokenName) {
			document.getElementsByName("tokenName").forEach(item => {
				item.value = data.csrf.tokenName;	
			});
			document.getElementsByName("tokenValue").forEach(item => {
				item.value = data.csrf.tokenValue;	
			});	
		}
	},

	getContentId: function(elems) {
		var breakException = {};
  		let elemId;
		try {
			elems.forEach(elem => {
				if(document.getElementById(elem) != null) {
					elemId = elem;
					throw breakException;
				}
			});
		} catch(e) {
			if (e !== breakException) throw e;
		}
		return elemId;
	},

	placeToken: function(data) { this.resetToken(data); },

	parseMsgs: function(data, parentId) {
		if(data.msgs) {
			let parent = document.getElementById(parentId);
			let div = document.createElement("div");
			div.innerHTML = data.msgs.trim();
			div.classList.add("alerts");
			parent.querySelectorAll(".alerts").forEach(e => e.remove());
			parent.prepend(div);
			this.scrollTo(".alerts");
		}
	},

	scrollTo: function(scrollTo, scrollDuration = 500) {
		if(typeof scrollTo === 'string') {
			var scrollToObj = document.querySelector(scrollTo);
			if(scrollToObj && typeof scrollToObj.getBoundingClientRect === 'function') {
				scrollTo = window.pageYOffset + scrollToObj.getBoundingClientRect().top;
			} else {
				throw `Error: No element found with the selector "${scrollTo}"`;
			}
		} else if(typeof scrollTo !== 'number') {
			scrollTo = 0;
		}
		var anchorHeightAdjust = 30;
		if(scrollTo > anchorHeightAdjust) {
			scrollTo = scrollTo - anchorHeightAdjust;
		}
		if(typeof scrollDuration !== 'number' || scrollDuration < 0) {
			scrollDuration = 1000;
		}
		var cosParameter = (window.pageYOffset - scrollTo) / 2,
			scrollCount = 0,
			oldTimestamp = window.performance.now();
		function step(newTimestamp) {
			var tsDiff = newTimestamp - oldTimestamp;
			if(tsDiff > 100) {
				tsDiff = 30;
			}
			scrollCount += Math.PI / (scrollDuration / tsDiff);
			if(scrollCount >= Math.PI) {
				return;
			}
			var moveStep = Math.round(scrollTo + cosParameter + cosParameter * Math.cos(scrollCount));
			window.scrollTo(0, moveStep);
			oldTimestamp = newTimestamp;
			window.requestAnimationFrame(step);
		}
		window.requestAnimationFrame(step);
	}
};

(function(bm) {
	// Replace sending all forms with a custom method
	const forms = [].slice.call(document.querySelectorAll(".scriptor-forms"));
	forms.forEach(item => {
		item.addEventListener("submit", e  => {
			e.preventDefault();
			let fData = new FormData(item);
			fData.append("formAction", item.action);
			fData.append("formMethod", item.method);
			fData.append("action", fData.get("actionName"));
			submit(fData, fData.get("function"));
		});
		
	});

	const token_loader = [].slice.call(document.querySelectorAll(".token-loader"));
	token_loader.forEach(item => {
		let fData = new FormData();
		fData.append("formAction", "./");
		fData.append("formMethod", "post");
		fData.append("action", "loadToken");
		submit(fData, 'placeToken');
	});

	// Submit forms
	async function submit(fData, callback) {
		const response = await fetch(fData.get("formAction"), {
			method: fData.get("formMethod"),
			body: fData
		});
		if(!response.ok) {
			const message = `An error has occured: ${response.status}`;
			throw new Error(message);
		}
		const result = await response.json();

		bm[callback](result);
	}
})(basicMethods);
