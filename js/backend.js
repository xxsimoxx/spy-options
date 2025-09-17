function spyo_help() {
	document.getElementById('contextual-help-link').click();
}

function spyo_get_ajax_option(option) {
	let data = {
		action     : 'spyoption',
		opt		   : option,
		remote_url : spyo.url,
		nonce      : spyo.nonce,
	};
	let dataJSON = (new URLSearchParams(data)).toString();
	var xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
			let response = JSON.parse(this.responseText);
			spyo_render_option(response.opt, response.value);
		}
	};
	xhttp.open('POST', spyo.url, true);
	xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded; charset=UTF-8');
	xhttp.send(dataJSON);
}

function spyo_get_option(option) {
	spyo_get_ajax_option(option);
}

function spyo_render_option(option, value) {
	// Code adapted from Tim Kaye - ClassicPress Directory Integration Plugin
	size = window.innerWidth * .75
	dialog = document.getElementById('option-modal');
	dialog.showModal();
	dialog.innerHTML = '<div id="option-container" style="width: ' + size + 'px;height: auto;" title="' + option + '"><button type="button" id="dialog-close-button" autofocus><span class="screen-reader-text">Close</span></button><div><h3>' + option + '</h3><pre>' + JSON.stringify(value, null, 2) + '</pre></div></div>';
	closeButton = dialog.querySelector('#dialog-close-button');
	closeButton.focus();
	closeButton.addEventListener('click', function() {
		dialog.close();
		dialog.querySelector('#option-container').remove();
	});
	dialog.addEventListener( 'keydown', function( e ) {
		if ( e.key === 'Escape' ) { // Remove modal contents
			if ( dialog.querySelector('#option-container') !== null ) {
				dialog.querySelector('#option-container').remove();
			}
		}
		else if (e.key === 'Enter' && e.target.id === 'dialog-close-button') { // Remove modal contents
			e.preventDefault();
			dialog.close();
			if ( dialog.querySelector('#option-container') !== null ) {
				dialog.querySelector('#option-container').remove();
			}
		}
	});
}

document.addEventListener("DOMContentLoaded", function(){
	const arrClass = document.querySelectorAll(".option-link");
	for (let i of arrClass) {
		i.addEventListener("click", (e) => {
			if (e.target.classList.contains("option-link")) {
				spyo_get_option(e.target.innerHTML)
			}
		})
	}

	document.getElementById('help-link').addEventListener("click", () => {
		document.getElementById('contextual-help-link').click()
	})
});
