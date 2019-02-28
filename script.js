var plus = document.querySelectorAll("#set-table th i.fa-plus-square-o");
for (var i = 0;i<plus.length;i++){
    plus[i].addEventListener("click", function(e){
        this.nextElementSibling.classList.add("active");
    });
}
var dopSet = document.querySelectorAll("#set-table th .dop_settings i.fa");
for (var i = 0;i<plus.length;i++){
    dopSet[i].addEventListener("click", function(e){
        this.parentElement.classList.remove("active");
    });
}

var files = document.querySelectorAll("#file-list input");
for (var i = files.length - 1; i >= 0; i--) {
	files[i].addEventListener("change", function(e){
		var cVal = document.querySelector("#file-list input:checked").value;
		document.location.href= moduleLink+"&import-file="+cVal;
	});
}