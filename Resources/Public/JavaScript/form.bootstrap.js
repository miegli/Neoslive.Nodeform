
(function () {


    var neosliveNodeformIncludesFilesLoading = {};

    function init() {

        var neosliveNodeformIncludesFiles = JSON.parse(jQuery(".neoslive-nodeform-includes").html());

        var inject = function (file) {



            if (file.type === 'javascript') {

                var s = document.createElement('script');
                s.type = 'text/javascript';
                s.async = file.async;
                s.src = file.url;
                var x = document.getElementsByTagName('script')[0];
                x.parentNode.insertBefore(s, x);
            }

            if (file.type === 'css') {

                var s = document.createElement('link');
                s.type = 'text/css';
                s.async = file.async;
                s.href = file.url;
                s.rel = 'stylesheet';
                var x = document.getElementsByTagName('link')[0];
                x.parentNode.insertBefore(s, x);
            }



        }


       // bootstrap
       // ----------------

        Object.keys(neosliveNodeformIncludesFiles).forEach(function (key) {

            Object.keys(neosliveNodeformIncludesFiles[key].checkbeforeload).forEach(function (k) {
                if (window.neosliveNodeformIncludesFilesLoading[key] === undefined && eval('typeof ' + neosliveNodeformIncludesFiles[key].checkbeforeload[k]) === 'undefined' ) {


                    window.neosliveNodeformIncludesFilesLoading[key] = neosliveNodeformIncludesFiles[key];

                    if (neosliveNodeformIncludesFiles[key]['onloaded'] !== undefined && neosliveNodeformIncludesFiles[key]['onloaded'] == true) {

                        jQuery( document ).ready(function( ) {
                            inject(neosliveNodeformIncludesFiles[key]);
                        });
                    } else {
                        inject(neosliveNodeformIncludesFiles[key]);
                    }


                }
            });

        });

    }


    jQuery( document ).ready(function() {
        if (window.neosliveNodeformIncludesFilesLoading == undefined) {
            window.neosliveNodeformIncludesFilesLoading = {};
            init();
        }
    });

})();
