if (NeosliveNodeformApp === undefined) {
    var NeosliveNodeformApp = true;

    (function () {


        'use strict';

        var NeosliveNodeformApp = angular.module('neosliveFormnode', ['ngCookies']).config(function ($interpolateProvider) {
            $interpolateProvider.startSymbol('}}}');
            $interpolateProvider.endSymbol('}}}');
        }).config(function ($anchorScrollProvider) {
            $anchorScrollProvider.disableAutoScrolling();
        });


        NeosliveNodeformApp.factory(
            "Form",
            ['$http', '$compile', '$timeout', function ($http, $compile, $timeout) {


                var FormResult;


                // define form instance model
                function FormInstance(self, nodeId, nodeProperties, scope) {

                    if (scope.data == undefined) scope.data = {};

                    // add properties
                    this.nodeId = nodeId;
                    this.element = jQuery(self);
                    this.selector = "#form-" + nodeId;
                    this.nodesInCondition = JSON.parse(nodeProperties.nodesInCondition);
                    this.apiUri = window.location.href;
                    this.scope = scope;
                    this.activeElement = this.element;
                    this.nodePath = nodeProperties.init.nodePath;
                    this.workspace = nodeProperties.init.workspace;
                    this.contextPath = nodeProperties.init.contextPath;

                    if (nodeProperties.instanceid > 0) {

                        // re-compile ng-bindings
                        this.element.find("*[ng-bind]").each(function (i) {
                            var linkFn = $compile(this);
                            var element = linkFn(scope);
                            jQuery(this).replaceWith(element);
                        });

                    }

                    return Object.seal(this);

                }

                // extend prototype functions
                FormInstance.prototype = {

                    getNodeId: function () {
                        return this.nodeId;
                    },

                    setNodeId: function (NodeId) {
                        this.nodeId = NodeId;
                    },

                    getNodePath: function () {
                        return this.nodePath;
                    },

                    getWorkspace: function () {
                        return this.workspace;
                    },

                    getContextPath: function () {
                        return this.contextPath;
                    },

                    getElement: function () {
                        return this.element;
                    },


                    setActiveElement: function (nodeId) {
                        this.activeElement = this.element.find("#node-" + nodeId);

                        return this;
                    },


                    hasCondition: function (nodeId) {

                        return (this.nodesInCondition[nodeId] === undefined ? false : true);
                    },

                    getSubmitDataAll: function (scope) {

                        var data = {};

                        angular.forEach(scope.dataAll, function (value, key) {

                            angular.forEach(value, function (v, k) {
                                data[k] = v;
                            });


                        });

                        return data;
                    },

                    getSubmitData: function (scope, context,formNodeId) {


                        var data = {};


                        angular.forEach(scope.data, function (value, key) {
                            if (jQuery(context).find("[data-node-id=" + key + "]").length > 0) {
                                if (value != '') data[key] = value;
                            }
                        });


                        // find parent data of nested select options
                        this.element.find(".neoslive-nodeform-select-parent-wrapper").each(function (i) {
                            var parentNodeId = jQuery(this).attr('data-node-id');
                            jQuery(this).find("input[data-parent-node-id=" + parentNodeId + "]").each(function (i) {
                                data[parentNodeId][jQuery(this).attr('data-node-id')] = jQuery(this).is(':checked') ? true : jQuery(this).is(':selected');
                            });
                        });


                        // remove expired option from select box
                        jQuery(context).find("select[data-node-id]").each(function (i) {
                            var key = jQuery(this).attr('data-node-id');
                            if (typeof data[key] == 'object') {
                                var d = [];
                                angular.forEach(data[key], function (v) {
                                    if (jQuery(context).find("select[data-node-id=" + key + "] option[data-node-id=" + v + "]").length > 0) {
                                        d.push(v);
                                    }
                                });
                                data[key] = d;
                            }
                        });


                        scope.submitdata = data;

                        if (formNodeId) scope.dataAll[formNodeId] = data;

                        return data;


                    },

                    submit: function (proceed,Form,context) {



                        var self = this;
                        var proceed = proceed !== undefined && proceed !== false ? true : false;

                        var applyNewContent = function (response, reinit, formNodeId,Form) {

                                var j = jQuery(response.data).find(".neoslive-nodeForm-angular-wrapper");



                                // filter out some critical tags
                                j.find("script,link").each(function () {
                                    jQuery(this).remove();
                                });

                                var compareleements = {
                                    'stable': [],
                                    'new': [],
                                    'old': []
                                };

                                // find dom elements in disappearing dom
                                self.element.find("*[id]").each(function () {
                                    var id = jQuery(this).attr('id');
                                    if (j.find("#" + id).length > 0) {
                                        compareleements.stable.push({id: id, element: jQuery(this)});
                                    } else {
                                        compareleements.old.push({id: id, element: jQuery(this)});
                                    }
                                });

                                // find dom elements in appearing dom
                                jQuery(j).find("*[id]").each(function () {
                                    var id = jQuery(this).attr('id');
                                    if (self.element.find("#" + id).length == 0) {
                                        compareleements.new.push({id: id, element: jQuery(this)});
                                    }
                                });


                                // handle old dom elements states
                                angular.forEach(compareleements.old, function (i) {
                                   jQuery(i.element).addClass(jQuery(self.element).attr('data-neoslive-out-css'));
                                });
                                angular.forEach(compareleements.stable, function (i) {
                                   jQuery(i.element).removeClass(jQuery(self.element).attr('data-neoslive-out-css'));
                                });


                                // get focuses object
                                var focused = jQuery(document).find(":focus");
                                var focusnew= false;







                                $timeout(function () {



                                    // preserve stable dom elements states
                                    angular.forEach(compareleements.stable, function (i,c) {
                                        jQuery(j).find("#" + i.id).replaceWith(i.element).html(jQuery(j).find("#" + i.id).html());
                                    });


                                    var linkFn = $compile(jQuery(j).html());
                                        self.element.html(linkFn(self.scope));


                                    angular.forEach(compareleements.new, function (i,c) {
                                        self.element.find("#" + i.id).addClass('neoslive-new-element').addClass(jQuery(self.element).attr('data-neoslive-in-css'));
                                        if (c == 0) {
                                            focusnew = i.id
                                        }
                                    });




                                    if (reinit != undefined) {
                                        // (re)-init form
                                        j.find(".neoslive-nodeForm-angular-wrapper-finisher").each(function () {
                                            self.scope.init(jQuery("#"+jQuery(this).attr('id')), JSON.parse(jQuery(this).find(".neoslive-nodeform-initvars").html()));
                                        });
                                    }


                                    // update steps if exist
                                    if (self.scope.steps) {
                                        jQuery("#"+jQuery(self.scope.steps).attr('id')).html(j.parent().find("#"+jQuery(self.scope.steps).attr('id')).html());
                                    }

                                        self.scope.setDataLabel(self.getNodeId());

                                       if (proceed === false) {
                                           self.scope.updateCheckboxes(Form[formNodeId]);
                                       }

                                        // re-enable submit buttons while processing data
                                       jQuery(Form[formNodeId].element).find(".neoslive-form-submit").attr('disabled',false);

                                        // set focus back
                                        if (jQuery("#"+focused.attr('id')).length>0) {
                                            jQuery("#"+focused.attr('id')).focus();
                                        } else {
                                            if (focusnew != false) jQuery("#"+focusnew).focus();
                                        }



                                }, jQuery(self.element).attr('data-neoslive-out-css') != '' ? 400 : 5);



                        }







                                // disable submit buttons while processing data
                                jQuery(self.element).find(".neoslive-form-submit").attr('disabled',true);



                                    if (proceed) {
                                        // proceed finishers
                                        $http({
                                            method: 'POST',
                                            url: this.apiUri,

                                            data: {
                                                formNodeId: self.getNodeId(),
                                                proceed: proceed,
                                                data: self.getSubmitData(self.scope, document,self.getNodeId()),
                                                dataAll: self.getSubmitDataAll(self.scope),
                                                'x-neoslive-nodeform-hash': self.scope.xNeosliveNodeformHash
                                            }
                                        }).then(function successCallback(response) {
                                            applyNewContent(response, true, self.getNodeId(),Form);
                                        }, function errorCallback(response) {
                                        });

                                    } else {




                                        $http({
                                            method: 'POST',

                                            cache: false,
                                            url: self.apiUri,
                                            data: {
                                                formNodeId: self.getNodeId(),
                                                data: self.getSubmitData(self.scope, context != undefined ? context : document,self.getNodeId()),
                                                dataAll: self.getSubmitDataAll(self.scope),
                                                preview: (jQuery("body.neos-preview-mode").length > 0 ? true : false),
                                                'x-neoslive-nodeform-hash': self.scope.xNeosliveNodeformHash
                                            }
                                        }).then(function successCallback(response) {


                                            self.scope.xNeosliveNodeformHash = response.headers()['x-neoslive-nodeform-hash'];


                                            if (response.headers()['x-neoslive-nodeform-apply'] != undefined) {

                                                // apply new content
                                                if (self.scope.lastApplyHash != response.headers()['x-neoslive-nodeform-apply']) {
                                                    applyNewContent(response, false, self.getNodeId(), Form);
                                                } else {
                                                    // re-enable submit buttons while processing data
                                                    jQuery(Form[self.getNodeId()].element).find(".neoslive-form-submit").attr('disabled',false);
                                                }
                                                self.scope.lastApplyHash = response.headers()['x-neoslive-nodeform-apply'];

                                            } else {

                                                // prepare new content for applying on last request
                                                self.scope.updateCheckboxes(self,jQuery(response.data).find("#form-"+self.getNodeId()));
                                                self.submit(false,Form,jQuery(response.data).find("#form-"+self.getNodeId()));

                                            }



                                        }, function errorCallback(response) {


                                        });



                                    }





                        return self;


                    }


                };


                // define Forms Repository, don't change it
                function Forms() {

                    this.elements = {};

                    Object.defineProperties(this, {
                        'Forms': {
                            init: function () {
                                return this;
                            },
                            getById: function () {
                                return this;
                            },
                            configurable: false,
                            enumerable: false
                        }
                    });


                    return this;
                }


                // extend repository prototype functions
                Forms.prototype = {


                    init: function (self, nodeId, nodeProperties, scope) {
                        this[nodeId] = new FormInstance(self, nodeId, nodeProperties, scope);
                        scope.formInstances[nodeId] = true;
                        return this[nodeId];

                    },

                    getFormByElement: function (id) {

                        return (typeof this.elements[id] === undefined ? null : this[this.elements[id]]);

                    },


                    getById: function (id) {
                        return (typeof this[id] === undefined ? null : this[id]);
                    }

                };


                FormResult = new Forms();
                return FormResult;


            }]);


        NeosliveNodeformApp.controller('frontendController', ['$scope', 'Form', function ($scope, Form) {

            $scope.steps = false;
            $scope.promised = {};
            $scope.nodesInCondition = {};
            $scope.datalabel = {};
            $scope.submitdata = {};
            $scope.datahelper = {};
            $scope.formInstances = {};
            $scope.dataAll = {};
            $scope.xNeosliveNodeformHash = 0;
            $scope.lastApplyHash = 0;

            $scope.init = function (self, v) {



               var f = Form.init(self, v.init.Identifier, v, $scope);
                if ($scope.steps === false) $scope.steps = f.element.parent().find('.neoslive-nodeform-steps');
                $scope.formElementId = v.init.Identifier;
                $scope.setDataLabel($scope.formElementId);


            }

            $scope.setDataLabel = function (formId) {



                Form[$scope.formElementId].element.find("option[data-node-id]").each(function (i) {
                    $scope.datalabel[jQuery(this).attr('data-node-id')] = jQuery(this).text();
                });

                Form[$scope.formElementId].element.find("input[data-node-id]").each(function (i) {
                    $scope.datalabel[jQuery(this).attr('data-node-id')] = Form[$scope.formElementId].element.find("label[for=node-" + jQuery(this).attr('data-node-id') + "]").text();
                });


            }

            $scope.updateCheckboxes = function (form,context) {


                var el = context == undefined ? form.element : context;

                jQuery(el).find("div.neoslive-nodeform-select-parent-wrapper").each(function (i) {

                    var parentNode = jQuery(this).attr('data-node-id');

                    $scope.data[parentNode] = {};
                    jQuery(el).find("div[data-node-id=" + parentNode + "] input[type=checkbox]").each(function (i) {
                        $scope.data[parentNode][jQuery(this).attr('data-node-id')] = $scope.data[jQuery(this).attr('data-node-id')] != undefined ? $scope.data[jQuery(this).attr('data-node-id')] : false;
                    });


                });


                angular.forEach($scope.datahelper, function (val, id) {

                    if (jQuery(el).find("#node-" + id + "").length == 0) {
                        $scope.data[id] = false;
                    } else {
                        $scope.data[id] = val;
                    }
                });



            }


            $scope.submit = function (id) {

                var formId = jQuery("#"+id).closest('.neoslive-nodeForm-angular-wrapper').attr('data-formnodeid');
                Form[formId].submit(true,Form);
                // deregister previous form
                delete $scope.formInstances[formId];


            }



            $scope.changed = function (nodeId, parentNode, tagname) {



                var mustreload = false;


                if (tagname == 'select') {
                    // do special update each option tag of select box
                    Form[$scope.formElementId].element.find("select[data-node-id=" + nodeId + "] option").each(function (i) {
                        $scope.data[jQuery(this).attr('data-node-id')] = jQuery(this).is(":selected");
                        if (mustreload == false && Form[$scope.formElementId].hasCondition(jQuery(this).attr('data-node-id'))) {
                            mustreload = true;
                        }
                    });
                }

                if (tagname == 'radio') {
                    // do special update each radio tag of form group
                    Form[$scope.formElementId].element.find("div[data-node-id=" + nodeId + "] input[type=radio]").each(function (i) {
                        $scope.data[jQuery(this).attr('data-node-id')] = $scope.data[nodeId] == jQuery(this).attr('data-node-id') ? true : false;
                        if (mustreload == false && Form[$scope.formElementId].hasCondition(jQuery(this).attr('data-node-id'))) {
                            mustreload = true;
                        }
                    });
                }

                if (tagname == 'checkbox') {
                    // do special update each checkbox of form group
                    $scope.data[parentNode] = {};
                    Form[$scope.formElementId].element.find("div[data-node-id=" + parentNode + "] input[type=checkbox]").each(function (i) {
                        $scope.data[parentNode][jQuery(this).attr('data-node-id')] = jQuery(this).is(":checked") ? true : false;
                        if (mustreload == false && (Form[$scope.formElementId].hasCondition(jQuery(this).attr('data-node-id')) || Form[$scope.formElementId].hasCondition(parentNode) )) {
                            mustreload = true;
                        }
                    });
                }

                if (tagname == 'checkboxsingle') {
                    // do special update each radio tag of form group
                    Form[$scope.formElementId].element.find("#node-" + nodeId).each(function (i) {
                        if (jQuery(this).is(":checked")) {
                            $scope.data[nodeId] = true;
                        } else {

                            $scope.data[nodeId] = false;
                        }
                    });
                }



                if (mustreload == true || Form[$scope.formElementId].hasCondition(nodeId) || Form[$scope.formElementId].hasCondition(parentNode)) {

                    angular.forEach($scope.promised, function (i, id) {
                        clearTimeout(id);
                    });

                    var timeoutId = setTimeout(function () {

                        angular.forEach($scope.formInstances, function (i, id) {
                            Form[id].submit(false,Form).setActiveElement(id)
                        });

                    }, 500);

                    $scope.promised[timeoutId] = true;


                }


            };


        }]).filter('placeholder', function () {
            return function (input, scope) {


                if (typeof input == 'object') {


                    var label = '';
                    angular.forEach(input, function (val, id) {


                        if (val != false && scope.datalabel[id] != undefined && (jQuery("body").find("*[data-node-id=" + id + "]").length > 0 | scope.submitdata[id] != undefined)) {
                            if (label !== '') label += ', ';
                            label += jQuery.trim(scope.datalabel[id]);
                        }

                        if (scope.datalabel[val] != undefined && (jQuery("body").find("*[data-node-id=" + val + "]").length > 0 | scope.submitdata[val] != undefined)) {
                            if (label !== '') label += ', ';
                            label += jQuery.trim(scope.datalabel[val]);
                        }


                    });

                    return label;


                } else {


                    if ((scope.datalabel[input] != undefined) && (jQuery("body").find("*[data-node-id=" + input + "]").length > 0 | scope.submitdata[input] != undefined)) {

                        if (jQuery("body").find("input#node-" + input).length == 0 && jQuery("body").find("option[data-node-id=" + input + "]").length == 0) {
                            // special handling for grouped options
                            return '';
                        }


                        return scope.datalabel[input];

                    } else {


                        return scope.datalabel[input] != undefined ? '' : input;

                    }


                }


            };
        });
        ;


        NeosliveNodeformApp.controller('backendController', ['$scope', '$http', '$compile', '$cookies', 'Form', function ($scope, $http, $compile, $cookies, Form) {


            $scope.dataLabels = {};
            $scope.formInstances = {};
            $scope.formElementId = false;

            $scope.togglestates = $cookies.getObject('togglestates') != undefined ? $cookies.getObject('togglestates') : {};


            $scope.init = function (self, v) {

                Form.init(self, v.init.Identifier, v, $scope);
                $scope.formElementId = v.init.Identifier;

                $scope.labels = v.labels;
                $scope.instanceid = v.init.instanceid;
                initBackendFunctions($scope.formElementId);
                loadPlaceholerLabels();

                document.addEventListener('Neos.NodeCreated', function (event) {
                    initBackendFunctions(v.init.Identifier);
                    loadPlaceholerLabels();
                });

            }


            // ----------------------------
            // general backend methods
            // ----------------------------

            var replacePlaceholder = function (elementId,form) {



                // update placeholders in dom
                jQuery(form.element).find('.neosliveformnode-placholder').each(function (i) {
                    var classname = jQuery(this).attr('class');
                    var myRegExp = new RegExp('(.*) neosliveformnode-placholder-(.*)-id(.*)', '');
                    var s = classname.match(myRegExp);

                    if (s[2] != undefined) {
                        jQuery(this).html($scope.dataLabels[s[2]]);
                    }

                });


            }


            var initBackendFunctions = function (formElementId) {






            }


            var loadPlaceholerLabels = function () {

                // load placeholder labels first
                $http({
                    method: 'GET',
                    url: '/neos/service/data-source/neoslive-form-conditioncontraints?node=' + Form[$scope.formElementId].getContextPath()
                }).then(function successCallback(response) {

                    if (response.status == 200) {
                        angular.forEach(response.data, function (i, v) {
                            $scope.dataLabels[v] = i.label;
                        });
                    }


                }, function errorCallback(response) {
                    // called asynchronously if an error occurs
                    // or server returns response with an error status.
                });

            }

            var nodeSelectedLoading = {};

            var nodeSelectedAfter = function (element) {

                // set opened flag
                jQuery(element).find("div[data-neoslive-collapsed]").each(function(i) {if (i==0) jQuery(this).addClass("neoslive-collapsed-out");});
                jQuery(element).closest("div[data-neoslive-collapsed]").addClass("neoslive-collapsed-out");



                var form = Form[jQuery(element).parentsUntil('.neoslive-nodeForm-angular-wrapper').parent().attr('data-formnodeid')];


                // make context bars

                jQuery("#neos-context-bar > div.chosen-container").hide();

                    if (form != undefined && (jQuery(element).find(" *[contenteditable=true]").length || jQuery(element).find("> .aloha-editable").length)) {

                    var formId = form.getNodeId();
                    var chosenSelector = "#neoslive-nodeform-placeholderSelectBox-" + formId + "_chosen";
                    chosenSelector = chosenSelector.replace(/-/g, "_");


                    var SelectBox = jQuery("#neoslive-nodeform-placeholderSelectBox-" + formId).length ? jQuery("#neoslive-nodeform-placeholderSelectBox-" + formId) : jQuery("<select/>").attr('id', "neoslive-nodeform-placeholderSelectBox-" + formId).addClass("neoslive-nodeform-placeholderSelectBox").appendTo("#neos-context-bar");



                        // load jquery chosen if not in exists
                        if (jQuery().chosen == undefined) {
                            var s = document.createElement('script');
                            s.type = 'text/javascript';

                            s.src = window.neosliveNodeformIncludesFilesLoading['jqueryChosen'].url;
                            var x = document.getElementsByTagName('script')[0];
                            x.parentNode.insertBefore(s, x);
                        }


                    if (SelectBox != undefined) {
                        // load placeholder select options
                        if (nodeSelectedLoading[element.attr('data-node-_identifier')] === undefined) {
                            nodeSelectedLoading[element.attr('data-node-_identifier')] = true;
                            $http({
                                method: 'GET',
                                url: '/neos/service/data-source/neoslive-form-conditioncontraints?node=' + form.getContextPath()
                            }).then(function successCallback(response) {


                                    SelectBox.find("option").each(function () {
                                        jQuery(this).remove();
                                    });
                                    SelectBox.append(jQuery("<option value='0' selected>" + $scope.labels.insertPlaceholder + "</option>"));
                                    angular.forEach(response.data, function (i, v) {
                                        SelectBox.append(jQuery("<option value='" + v + "'>" + i.label + "</option>"));
                                        $scope.dataLabels[v] = i.label;
                                    });

                                    SelectBox.trigger("chosen:updated");

                                    SelectBox.chosen({
                                        disable_search: true,
                                        no_results_text: "Oops, nothing found!",
                                        width: "auto"
                                    }).trigger("chosen:updated").change(function (e) {
                                        var val = jQuery(this).val();
                                        if (val != '0' && Aloha.activeEditable) {
                                            var val = jQuery(e.target).val();
                                            var html = jQuery('<span><a href="#" class="neosliveformnode-placholder neosliveformnode-placholder-' + val + '-id">' + $scope.dataLabels[val] + '</a></span>');
                                            var range = Aloha.Selection.getRangeObject();
                                            GENTICS.Utils.Dom.insertIntoDOM(html, range, jQuery(Aloha.activeEditable.obj));
                                            replacePlaceholder(element,form);
                                            jQuery(this).val('0').trigger('chosen:updated');
                                        }

                                    });
                                    jQuery("#neos-context-bar > div.chosen-container").hide();
                                    jQuery(chosenSelector).show();
                                    replacePlaceholder(element.attr('data-node-_identifier'),form);



                                delete nodeSelectedLoading[element.attr('data-node-_identifier')];


                            }, function errorCallback(response) {
                                // called asynchronously if an error occurs
                                // or server returns response with an error status.

                            });
                        }
                    }



                }


            }


            // ----------------------------
            // backend functions
            // ----------------------------


            $scope.toggle = function (nodeid) {


                if ($scope.togglestates[nodeid] === undefined | $scope.togglestates[nodeid] == false) {
                    $scope.togglestates[nodeid] = true;
                } else {
                    $scope.togglestates[nodeid] = false;
                }

            }


            // ----------------------------
            // register events
            // ----------------------------


            // Page Module Loaded listener
            if (typeof document.addEventListener === 'function') {
                document.addEventListener('Neos.PageLoaded', function (event) {
                    // $scope.init[$scope.formElementId].instanceid++;

                }, false);
            }

            // Content Module Loaded listener
            if (typeof document.addEventListener === 'function') {
                document.addEventListener('Neos.NodeCreated', function (event) {

                    var element = jQuery(event.detail.element);
                    var elid = element.attr('data-node-_identifier');

                    if (Form[$scope.formElementId].getElement().find("[data-node-_identifier=" + elid + "]").length) {
                        nodeSelectedAfter(element);
                    }

                }, false);
            }


            // node select listener
            if (typeof document.addEventListener === 'function') {
                document.addEventListener('Neos.NodeSelected', function (event) {
                    var element = jQuery(event.detail.element);
                    nodeSelectedAfter(element);
                }, false);
            }

            document.addEventListener('Neos.PageLoaded', function (event) {
                initApplication();
            });



        }]);


        function initApplication() {


            jQuery(".neoslive-nodeForm-angular-wrapper").each(function (i) {
                var self = this;


                if (jQuery("body.neos-backend").length) {

                       jQuery("body").click(function(e) {
                           if (jQuery(e.target).hasClass('neos-backend') || jQuery(e.target).hasClass('.neos-not-inline-editable')) {
                           jQuery(document).find("div[data-neoslive-collapsed]").removeClass("neoslive-collapsed-out");
                           }
                       }) ;



                    if (jQuery(self).hasClass("neosliveFormnode-loaded") === false) {
                        // Do stuff
                        jQuery(self).addClass('neosliveFormnode-loaded');
                        jQuery(self).attr('ng-controller', 'backendController');
                        try {
                            angular.bootstrap(jQuery(self), ['neosliveFormnode']);
                        } catch (e) {}

                    }

                } else {

                    if (jQuery(self).hasClass("neosliveFormnode-loaded") === false) {
                        jQuery(self).addClass("neosliveFormnode-loaded").attr('ng-controller', 'frontendController');
                        try {
                            angular.bootstrap(jQuery(self), ['neosliveFormnode']);
                        } catch (e) {
                        }
                    }
                }

                jQuery(this).addClass('neoslive-nodeform');
                if (jQuery(this).find(".neoslive-nodeform-initvars").html() != undefined) {
                    jQuery(self).scope().init(self, JSON.parse(jQuery(this).find(".neoslive-nodeform-initvars").html()));
                }

            });

        }



        // first init
        initApplication();



    })();


}

