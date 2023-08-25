var roseversion = '2024100700'; // Moodle 4.4.3+ version
console.log("Rose Version: " + roseversion);

import Cookies from 'https://cdn.jsdelivr.net/npm/js-cookie@3.0.5/+esm';

// Requires jQuery (can use both $ or jQuery)
require(['jquery'], function (jQuery) {
    /**
     *
     * Execute key code.
     * May 4th, 2018
     *
     */
    window.rosekeycode = function (keys, elem) {
        jQuery.each(keys, function(index, value) {
            try {
                var keyboardEvent = new KeyboardEvent('keydown', {
                    bubbles: true
                });
                Object.defineProperty(keyboardEvent, 'keyCode', {
                    get: function() {
                        return this.keyCodeVal;
                    }
                });
                keyboardEvent.keyCodeVal = value;
                document.body.dispatchEvent(keyboardEvent);
            } catch (e) { // IE likes this one.
                var eventObj = document.createEventObject ?
                    document.createEventObject() : document.createEvent("Events");

                if (eventObj.initEvent) {
                    eventObj.initEvent("keydown", true, true);
                }
                eventObj.keyCode = value;
                eventObj.which = value;
                elem.dispatchEvent ? elem.dispatchEvent(eventObj) : elem.fireEvent("onkeydown", eventObj);
            }
        });

        try {
            var keyboardEvent = new KeyboardEvent('keyup', {
                bubbles: true
            });
            document.body.dispatchEvent(keyboardEvent);
        } catch (e) { // IE likes this one.
            var eventObj = document.createEventObject ? document.createEventObject() : document.createEvent("Events");
            if (eventObj.initEvent) {
                eventObj.initEvent("keyup", true, true);
            }
            elem.dispatchEvent ? elem.dispatchEvent(eventObj) : elem.fireEvent("onkeyup", eventObj);
        }
    }

    /**
     *
     * Wait until element is available.
     * May 4th, 2018
     *
     */
    window.waitFor = function(selector, callback, maxTimes = false) {
        if (jQuery(selector).length) {
            callback();
        } else {
            if (maxTimes === false || maxTimes > 0) {
                maxTimes != false && maxTimes--;
                setTimeout(function() {
                    waitFor(selector, callback, maxTimes);
                }, 100);
            }
        }
    };

    /**
     *
     * Get variable from URL.
     * May 4th, 2018
     *
     */
    window.getQueryVariable = function(variable) {
        var query = window.location.search.substring(1);
        var vars = query.split("&");
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split("=");
            if (pair[0] == variable) {
                return pair[1];
            }
        }
        return (false);
    }

    /**
     *
     * Make hyperlinks more accessible.
     * May 4th, 2018
     *
     */
    function togglerhlinknotice() {
        $("#region-main a:not(a:has(img)), .block_html a:not(a:has(img))").not(
            ".moodle-actionmenu a, .disabled a, a[class*='nav'], .btn, .section-navigation a, footer a, .breadcrumb a, .tree_item a, a[id*='label'], a:has(i), .tree_item"
        ).each(function() {
            if ($(this).prev().find('img').length == 0 &&
                $(this).prev().find('i').length == 0 &&
                $(this).text().length > 0 &&
                !$(this).find(".card-img-top").length) {
                if (this.hostname && this.hostname !== location.hostname) {
                    $(this).toggleClass("rhlinknotice_external");
                } else {
                    $(this).toggleClass("rhlinknotice");
                }
            } else {
                if ($(this).prev()[0] !== undefined &&
                    $(this).prev()[0].nextSibling !== undefined &&
                    $(this).prev()[0].nextSibling.length > 3) {
                    if (this.hostname && this.hostname !== location.hostname) {
                        $(this).toggleClass("rhlinknotice_external");
                    } else {
                        $(this).toggleClass("rhlinknotice");
                    }
                }
            }
        });
    }

    function process_unicode_buttons() {
        $(".unicodehelper input").each(function() {
            $(this).on('mousedown', function(event) {
                // Attempt to refocus on nearest editor.
                if ($(this).closest('span.answer').find("input").length) { //short answer fields
                    if (document.activeElement !== $(this).closest('span.answer').find("input")[0]) {
                        $(this).closest('span.answer').find("input").first().focus();
                    }
                } else if ($(this).parent().parent().find("input[name*='answer[']").length) {
                    if (document.activeElement !== $(this).parent().parent().find("input[name*='answer[']")[0]) {
                        $(this).parent().parent().find("input[name*='answer[']").focus();
                    }
                } else if ($(this).parent("div").parent("div").find("div [contenteditable]").length) {
                    if (document.activeElement !== $(this).parent("div").parent("div").find("div [contenteditable]")[0]) {
                        $(this).parent("div").parent("div").find("div [contenteditable]").focus();
                        setEndOfContenteditable($(this).parent("div").parent("div").find("div [contenteditable]")[0]);
                    }
                } else if ($(this).parent("div").parent("div").find("iframe").length) {
                    if (document.activeElement !== $(this).parent("div").parent("div").find("div [contenteditable]")[0]) {
                        $(this).parent("div").parent("div").find("iframe").focus();
                    }
                } else if ($(this).parent("div").parent("div").find('*[data-fieldtype="editor"] textarea').length) {
                    if (document.activeElement !== $(this).parent("div").parent("div").find('*[data-fieldtype="editor"] textarea')[0]) {
                        $(this).parent("div").parent("div").find('*[data-fieldtype="editor"] textarea').first().focus();
                    }
                }

                // Attempt to insert text into Atto editor.
                if (document.execCommand("insertText", false, $(this).val())) {
                    // insert successfull.
                } else if (document.activeElement.tagName.toLowerCase() === 'input') { // input field fallback for browsers that don't support insertText;
                    $(document.activeElement).val($(document.activeElement).val() + $(this).val());
                } else if (document.activeElement.contentDocument !== undefined && document.activeElement.contentDocument.execCommand("insertText", false, $(this).val())) { // TinymCE editor is in an iframe.
                    // insert successfull.
                } else {
                    // Browser doesn't support insertText and we default back to a copy/paste method.
                    var $temp = $("<div class='unicodehelper_copynotice'>" + $(this).val() + " Copied</div>");
                    $("body").append($temp);
                    $(".unicodehelper_copynotice").fadeOut(1000, function() {
                        $(this).remove();
                    });

                    var node = document.createTextNode($(this).val()),
                        selection = window.getSelection(),
                        range = document.createRange(),
                        clone = null;

                    if (selection.rangeCount > 0) {
                        clone = selection.getRangeAt(selection.rangeCount - 1).cloneRange();
                    }

                    document.body.appendChild(node);
                    selection.removeAllRanges();
                    range.selectNodeContents(node);
                    selection.addRange(range);
                    document.execCommand("copy");
                    node.remove();
                }
                event.preventDefault();
                return false;
            });
        });
    }

    function create_unicode_button_set(name, set) {
        if ($('*[data-fieldtype="editor"]').length > 0 || $('.qtype_essay_response').length > 0 || $('.answer input').length > 0 || $("input[name*='answer[']").length > 0) {
            if ($('.' + name).length > 0) { // if it is already showing, remove it.
                $('.' + name).remove();
            } else {
                $('.unicodehelper').remove(); // removes a different unicode set first.

                $('*[data-fieldtype="editor"], .qtype_essay_response, span.answer input, .d-flex:has(input[name*="answer["])').not('*[data-fieldtype="editor"] textarea').after("<div class='unicodehelper " + name + " col-md-3'></div><div class='unicodehelper " + name + " col-md-9 felement'></div>");
                set.forEach(function(item) {
                    $('.unicodehelper.felement').append("<input type='button' value='" + item + "' />");
                });

                process_unicode_buttons();
            }
        }
    }

    function setEndOfContenteditable(contentEditableElement) {
        var range, selection;
        if (document.createRange) { //Firefox, Chrome, Opera, Safari, IE 9+
            range = document.createRange(); //Create a range (a range is a like the selection but invisible)
            range.selectNodeContents(contentEditableElement); //Select the entire contents of the element with the range
            range.collapse(false); //collapse the range to the end point. false means collapse to end rather than the start
            selection = window.getSelection(); //get the selection object (allows you to change selection)
            selection.removeAllRanges(); //remove any selections already made
            selection.addRange(range); //make the range you have just created the visible selection
        } else if (document.selection) { //IE 8 and lower
            range = document.body.createTextRange(); //Create a range (a range is a like the selection but invisible)
            range.moveToElementText(contentEditableElement); //Select the entire contents of the element with the range
            range.collapse(false); //collapse the range to the end point. false means collapse to end rather than the start
            range.select(); //Select the range (make it the visible selection
        }
    }

    var down = {};
    var isControl = false;
    var isAlt = false;
    $(document).keydown(function(e) {
        down[e.keyCode] = true;
    }).keyup(function(e) {
        if (down[91] || down[224] || down[17]) {
            isControl = true;
        }
        if (down[18]) {
            isAlt = true;
        }

        /**
         *
         * Sets the  CTRL + ALT + E as a special shortcut
         * to toggle Editing Mode on and off
         * Requested by Bill Kline on 10/21/2014
         *
         */
        if (isControl && isAlt && down[69]) {
            if ($(":submit[value='Turn editing on']").length) {
                $(":submit[value='Turn editing on']").click();
            } else if ($(":submit[value='Turn editing off']").length) {
                $(":submit[value='Turn editing off']").click();
            } else if ($("a:contains('Turn editing on')").length) {
                $("a:contains('Turn editing on')")[0].click();
            } else if ($("a:contains('Turn editing off')").length) {
                $("a:contains('Turn editing off')")[0].click();
            } else if ($("button:contains('Customi')").length) {
                $("button:contains('Customi')").click();
            } else if ($("button:contains('Stop customi')").length) {
                $("button:contains('Stop customi')").click();
            } else if ($("button:contains('Turn editing on')").length) {
                $("button:contains('Turn editing on')")[0].click();
            } else if ($("button:contains('Turn editing off')").length) {
                $("button:contains('Turn editing off')")[0].click();
            } else if ($(".editmode-switch-form").length) {
                $(".editmode-switch-form input").trigger('click');
            }
        }

        /**
         *
         * Musichelper CTRL + ALT + N
         * Mar. 3rd, 2017
         *
         */
        if (isControl && isAlt && down[78]) {
            var items = ['&#x602;', '&#x2260;', '&#x226d;', '&#x2669;', '&#x266a;', '&#x266b;', '&#x266c;', '&#x266d;', '&#x266e;', '&#x266f;',
                '&#x1d100;', '&#x1d101;', '&#x1d102;', '&#x1d103;', '&#x1d104;', '&#x1d105;', '&#x1d106;', '&#x1d107;', '&#x1d108;', '&#x1d109;',
                '&#x1d10a;', '&#x1d10b;', '&#x1d10c;', '&#x1d10d;', '&#x1d10e;', '&#x1d10f;', '&#x1d110;', '&#x1d111;', '&#x1d112;', '&#x1d113;',
                '&#x1d114;', '&#x1d115;', '&#x1d116;', '&#x1d117;', '&#x1d118;', '&#x1d119;', '&#x1d11a;', '&#x1d11b;', '&#x1d11c;', '&#x1d11d;',
                '&#x1d11e;', '&#x1d11f;', '&#x1d120;', '&#x1d121;', '&#x1d122;', '&#x1d123;', '&#x1d124;', '&#x1d125;', '&#x1d126;', '&#x1d129;',
                '&#x1d12a;', '&#x1d12b;', '&#x1d12c;', '&#x1d12d;', '&#x1d12e;', '&#x1d12f;', '&#x1d130;', '&#x1d131;', '&#x1d132;', '&#x1d133;',
                '&#x1d134;', '&#x1d135;', '&#x1d136;', '&#x1d137;', '&#x1d138;', '&#x1d139;', '&#x1d13a;', '&#x1d13b;', '&#x1d13c;', '&#x1d13d;',
                '&#x1d13e;', '&#x1d13f;', '&#x1d140;', '&#x1d141;', '&#x1d142;', '&#x1d143;', '&#x1d144;', '&#x1d145;', '&#x1d146;', '&#x1d147;',
                '&#x1d148;', '&#x1d149;', '&#x1d14a;', '&#x1d14b;', '&#x1d14c;', '&#x1d14d;', '&#x1d14e;', '&#x1d14f;', '&#x1d150;', '&#x1d151;',
                '&#x1d152;', '&#x1d153;', '&#x1d154;', '&#x1d155;', '&#x1d156;', '&#x1d157;', '&#x1d158;', '&#x1d159;', '&#x1d15a;', '&#x1d15b;',
                '&#x1d15c;', '&#x1d15d;', '&#x1d15e;', '&#x1d15f;', '&#x1d160;', '&#x1d161;', '&#x1d162;', '&#x1d163;', '&#x1d164;', '&#x1d165;',
                '&#x1d166;', '&#x1d167;', '&#x1d168;', '&#x1d169;', '&#x1d16a;', '&#x1d16b;', '&#x1d16c;', '&#x1d16d;', '&#x1d16e;', '&#x1d16f;',
                '&#x1d170;', '&#x1d171;', '&#x1d172;', '&#x1d17b;', '&#x1d17c;', '&#x1d17d;', '&#x1d17e;', '&#x1d17f;', '&#x1d180;', '&#x1d181;',
                '&#x1d182;', '&#x1d183;', '&#x1d184;', '&#x1d185;', '&#x1d186;', '&#x1d187;', '&#x1d188;', '&#x1d189;', '&#x1d18a;', '&#x1d18b;',
                '&#x1d18c;', '&#x1d18d;', '&#x1d18e;', '&#x1d18f;', '&#x1d190;', '&#x1d191;', '&#x1d192;', '&#x1d193;', '&#x1d194;', '&#x1d195;',
                '&#x1d196;', '&#x1d197;', '&#x1d198;', '&#x1d199;', '&#x1d19a;', '&#x1d19b;', '&#x1d19c;', '&#x1d19d;', '&#x1d19e;', '&#x1d19f;',
                '&#x1d1a0;', '&#x1d1a1;', '&#x1d1a2;', '&#x1d1a3;', '&#x1d1a4;', '&#x1d1a5;', '&#x1d1a6;', '&#x1d1a7;', '&#x1d1a8;', '&#x1d1a9;',
                '&#x1d1aa;', '&#x1d1ab;', '&#x1d1ac;', '&#x1d1ad;', '&#x1d1ae;', '&#x1d1af;', '&#x1d1b0;', '&#x1d1b1;', '&#x1d1b2;', '&#x1d1b3;',
                '&#x1d1b4;', '&#x1d1b5;', '&#x1d1b6;', '&#x1d1b7;', '&#x1d1b8;', '&#x1d1b9;', '&#x1d1ba;', '&#x1d1bb;', '&#x1d1bc;', '&#x1d1bd;',
                '&#x1d1be;', '&#x1d1bf;', '&#x1d1c0;', '&#x1d1c1;', '&#x1d1c2;', '&#x1d1c3;', '&#x1d1c4;', '&#x1d1c5;', '&#x1d1c6;', '&#x1d1c7;',
                '&#x1d1c8;', '&#x1d1c9;', '&#x1d1ca;', '&#x1d1cb;', '&#x1d1cc;', '&#x1d1cd;', '&#x1d1ce;', '&#x1d1cf;', '&#x1d1d0;', '&#x1d1d1;',
                '&#x1d1d2;', '&#x1d1d3;', '&#x1d1d4;', '&#x1d1d5;', '&#x1d1d6;', '&#x1d1d7;', '&#x1d1d8;', '&#x1d1d9;', '&#x1d1da;', '&#x1d1db;',
                '&#x1d1dc;', '&#x1d1dd;', '&#x1d1de;', '&#x1d1df;', '&#x1d1e0;', '&#x1d1e1;', '&#x1d1e2;', '&#x1d1e3;', '&#x1d1e4;', '&#x1d1e5;',
                '&#x1d1e6;', '&#x1d1e7;', '&#x1d1e8;', '&#x1f398;', '&#x1f399;', '&#x1f39a;', '&#x1f39b;', '&#x1f39c;', '&#x1f39d;'
            ];

            create_unicode_button_set("musichelper", items);
        }

        /**
         *
         * Mathhelper CTRL + ALT + M
         * Mar. 3rd, 2017
         *
         */
        if (isControl && isAlt && down[77]) {
            var items = ['&#8704;', '&#8705;', '&#8706;', '&#8707;', '&#8708;', '&#8709;', '&#8710;', '&#8711;', '&#8712;', '&#8713;', '&#8714;', '&#8715;', '&#8716;', '&#8717;', '&#8718;', '&#8719;', '&#8720;', '&#8721;', '&#8722;', '&#8723;', '&#8724;', '&#8725;', '&#8726;', '&#8727;', '&#8728;', '&#8729;', '&#8730;', '&#8731;', '&#8732;', '&#8733;', '&#8734;', '&#8735;', '&#8736;', '&#8737;', '&#8738;', '&#8739;', '&#8740;', '&#8741;', '&#8742;', '&#8743;', '&#8744;', '&#8745;', '&#8746;', '&#8747;', '&#8748;', '&#8749;', '&#8750;', '&#8751;', '&#8752;', '&#8753;', '&#8754;', '&#8755;', '&#8756;', '&#8757;', '&#8758;', '&#8759;', '&#8760;', '&#8761;', '&#8762;', '&#8763;', '&#8764;', '&#8765;', '&#8766;', '&#8767;', '&#8768;', '&#8769;', '&#8770;', '&#8771;', '&#8772;', '&#8773;', '&#8774;', '&#8775;', '&#8776;', '&#8777;', '&#8778;', '&#8779;', '&#8780;', '&#8781;', '&#8782;', '&#8783;', '&#8784;', '&#8785;', '&#8786;', '&#8787;', '&#8788;', '&#8789;', '&#8790;', '&#8791;', '&#8792;', '&#8793;', '&#8794;', '&#8795;', '&#8796;', '&#8797;', '&#8798;', '&#8799;', '&#8800;', '&#8801;', '&#8802;', '&#8803;', '&#8804;', '&#8805;', '&#8806;', '&#8807;', '&#8808;', '&#8809;', '&#8810;', '&#8811;', '&#8812;', '&#8813;', '&#8814;', '&#8815;', '&#8816;', '&#8817;', '&#8818;', '&#8819;', '&#8820;', '&#8821;', '&#8822;', '&#8823;', '&#8824;', '&#8825;', '&#8826;', '&#8827;', '&#8828;', '&#8829;', '&#8830;', '&#8831;', '&#8832;', '&#8833;', '&#8834;', '&#8835;', '&#8836;', '&#8837;', '&#8838;', '&#8839;', '&#8840;', '&#8841;', '&#8842;', '&#8843;', '&#8844;', '&#8845;', '&#8846;', '&#8847;', '&#8848;', '&#8849;', '&#8850;', '&#8851;', '&#8852;', '&#8853;', '&#8854;', '&#8855;', '&#8856;', '&#8857;', '&#8858;', '&#8859;', '&#8860;', '&#8861;', '&#8862;', '&#8863;', '&#8864;', '&#8865;', '&#8866;', '&#8867;', '&#8868;', '&#8869;', '&#8870;', '&#8871;', '&#8872;', '&#8873;', '&#8874;', '&#8875;', '&#8876;', '&#8877;', '&#8878;', '&#8879;', '&#8880;', '&#8881;', '&#8882;', '&#8883;', '&#8884;', '&#8885;', '&#8886;', '&#8887;', '&#8888;', '&#8889;', '&#8890;', '&#8891;', '&#8892;', '&#8893;', '&#8894;', '&#8895;', '&#8896;', '&#8897;', '&#8898;', '&#8899;', '&#8900;', '&#8901;', '&#8902;', '&#8903;', '&#8904;', '&#8905;', '&#8906;', '&#8907;', '&#8908;', '&#8909;', '&#8910;', '&#8911;', '&#8912;', '&#8913;', '&#8914;', '&#8915;', '&#8916;', '&#8917;', '&#8918;', '&#8919;', '&#8920;', '&#8921;', '&#8922;', '&#8923;', '&#8924;', '&#8925;', '&#8926;', '&#8927;', '&#8928;', '&#8929;', '&#8930;', '&#8931;', '&#8932;', '&#8933;', '&#8934;', '&#8935;', '&#8936;', '&#8937;', '&#8938;', '&#8939;', '&#8940;', '&#8941;', '&#8942;', '&#8943;', '&#8944;', '&#8945;', '&#8946;', '&#8947;', '&#8948;', '&#8949;', '&#8950;', '&#8951;', '&#8952;', '&#8953;', '&#8954;', '&#8955;', '&#8956;', '&#8957;', '&#8958;', '&#8959;'];

            create_unicode_button_set("mathhelper", items);
        }

        /**
         *
         * Diacritical Characters CTRL + ALT + D
         * Sept. 22nd, 2020
         *
         */
        if (isControl && isAlt && down[68]) {
            var items = ['&aacute;', '&eacute;', '&iacute;', '&oacute;', '&uacute;', '&ntilde;', '&uuml;', '&iexcl;', '&Aacute;', '&Eacute;', '&Iacute;', '&Oacute;', '&Uacute;', '&Ntilde;', '&Uuml;', '&iquest;'];

            create_unicode_button_set("diacriticalhelper", items);
        }

        /**
         *
         * Broken link finder && accessibility checker CTRL + ALT + L
         * Mar. 3rd, 2017
         *
         */
        if (isControl && isAlt && down[76]) {
            console.clear();
            let host = window.location.host;
            var links = $(".contentwithoutlink a[href*='" + host + "'], .contentafterlink a[href*='" + host + "'], .generalbox a[href*='" + host + "'], #pageintro a[href*='" + host + "']").not(".autolink");
            if (links.length > 0) {
                console.log("The following links should be checked.");
                $.each(links, function(index, value) {
                    console.log(value);
                });
            } else {
                console.log("No link issues found.")
            }

            var images1 = $('img[alt=""]').not(".icon, .iconlarge, .activityicon");
            if (images1.length > 0) {
                images1.css({
                    border: "5px solid yellow"
                });
                console.log("The following images do not have alt text added.");
                $.each(images1, function(index, value) {
                    console.log(value);
                });
            }

            var images2 = $('img[src*="draftfile.php"]');
            if (images2.length > 0) {
                images1.css({
                    border: "8px solid red"
                });
                console.log("The following images are links to unsaved (draft) files and will not work.");
                $.each(images1, function(index, value) {
                    console.log(value);
                });
            }

            if (images1.length > 0 && images2.length > 0) {
                console.log("No image issues found.")
            }
        }

        /**
         *
         * Sets the  CTRL + ALT + S as a special shortcut
         * to toggle Student View on and off
         *
         */
        if (isControl && isAlt && down[83]) {
            if ($("a.dropdown-item:contains('Switch role to...')").length > 0) {
                var courseid = $('body').attr("class").match(/course-[0-9]+/g)[0].replace("course-", "");
                $('<iframe src="' + M.cfg.wwwroot + '/course/switchrole.php?id=' + courseid + '&switchrole=5&sesskey=' + M.cfg.sesskey + '" style="display:none" id="tempframe"></iframe>').appendTo('body');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }

            if ($("a.dropdown-item:contains('Return to my normal role')").length > 0) {
                $("a.dropdown-item:contains('Return to my normal role')")[0].click();
            }
        }

        /**
         *
         * Sets the  CTRL + ALT + P as a special shortcut
         * to toggle printable quiz view
         *
         */
        if (isControl && isAlt && down[80]) {
            if ($('.navbar').is(":hidden")) {
                // Show Everything.
                $('.navbar').show();
                $('#page').show();
                $('#page-footer').show();
                $('#printable_quiz').remove();
            } else {
                // Hide everything.
                $('.navbar').hide();
                $('#page').hide();
                $('#page-footer').hide();

                $('.navbar').before('<div id="printable_quiz" style="margin: 40px;"></div>');
                var quizname = $(".breadcrumb-item a[title='Quiz']").text();
                $('#printable_quiz').append('<h1>' + quizname + '</h1><br /><br />');
                var i = 1;
                $($(".qno")).each(function() {
                    var newquestion = $('<div class="printable_question" style="overflow: auto;">');
                    $('<strong>Question ' + i + '</strong>').appendTo(newquestion);
                    $($('.qtext')[i - 1 + i - 1]).clone().appendTo(newquestion);

                    if ($($('.que')[i - 1]).hasClass('calculated') || $($('.que')[i - 1]).hasClass('essay') || $($('.que')[i - 1]).hasClass('numerical') || $($('.que')[i - 1]).hasClass('shortanswer')) {
                        $('<div class="ablock"><br /><br /><br /><br /><br /><br /><br /></div>').appendTo(newquestion);
                    } else {
                        $($('.ablock')[i - 1 + i - 1]).clone().appendTo(newquestion);
                    }
                    $('<br /><br /><br />').appendTo(newquestion);
                    $(newquestion).appendTo('#printable_quiz');
                    i++;
                });
            }
        }

        /**
         *
         * Make hyperlinks more accessible. CTRL + ALT + B
         * May 4th, 2018
         *
         */
        if (isControl && isAlt && down[66]) {
            togglerhlinknotice();

            if (Cookies.get("rhlinknotice") != null) {
                Cookies.remove("rhlinknotice");
            } else {
                Cookies.set("rhlinknotice", true);
            }
        }

        /**
         *
         * L&T Help shortcut. CTRL + ALT + H
         * August 21st, 2024
         *
         */
        if (isControl && isAlt && down[72]) {
            let datetime = new Date().toLocaleString();
            let courseid = $('body').attr("class").match(/course-[0-9]+/g)[0].replace("course-", "");
            let coursename = $('a[href$="/course/view.php?id=' + courseid + '"][title!=""][title]:lt(1)').prop('title');
            if (typeof coursename === 'undefined') {
                coursename = $('title').text();
            }
            let subject = "Help requested in course: " + coursename;
            let template = `Time of request: ` + datetime + `\nIssue found in course: ` + coursename + ` -  ` + M.cfg.wwwroot + `/course/view.php?id=` + courseid + `\nExact URL of issue: ` + window.location.href + `.\n\nDescribe the issue and how to reproduce it:\n`;
            window.location.href = "mailto:LearningAndTechnology@rose-hulman.edu?subject=" + subject + "&body=" + encodeURIComponent(template);
        }

        /**
         *
         * Toggle AltFormat icons. CTRL + ALT + C
         * August 29th, 2022
         *
         */
        if (isControl && isAlt && down[67]) {
            if (Cookies.get("rhhidebrickfieldicon") != null) {
                Cookies.remove("rhhidebrickfieldicon");
                $(".local-bfaltformat-access-button").parent("span").show();
                $('.fa-retweet').parent('a').html(function(_, ctx) {
                    return ctx.replace("Show AltFormat", "Hide AltFormat");
                });
            } else {
                Cookies.set("rhhidebrickfieldicon", true, {
                    expires: 365
                });
                $(".local-bfaltformat-access-button").parent("span").hide();
                $('.fa-retweet').parent('a').html(function(_, ctx) {
                    return ctx.replace("Hide AltFormat", "Show AltFormat");
                });
            }
        }

        for (var keycodes in down) {
            delete down[keycodes];
        }
        isControl = false;
        isAlt = false;
        e.preventDefault();
    });

    /**
     *
     * Add a shortcut helper
     * Aug 8th, 2018
     *
     */
    function register_shortcut(iconname, codes, title) {
        var isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        var altkey = isMac ? "OPTION" : "ALT";
        var ctrlkey = isMac ? "⌘" : "Ctrl";
        var toggle = new Object;
        var icon = '<i class="icon fa fa-' + iconname + ' fa-fw " aria-hidden="true" aria-label="' + title + '"></i>';
        toggle["title"] = icon + ctrlkey + " + " + altkey + " + " + title;
        toggle["codes"] = codes;
        if (Object.entries(toggle).length) {
            return toggle;
        }
        return false;
    }

    $(function() {
        $(document).ready(function() {
            setTimeout(function() {
                var available_shortcuts = new Array;
                /**
                 *
                 * Sets the CTRL ALT E as a special shortcut
                 * to toggle Editing Mode on and off
                 * Requested by Bill Kline on 10/21/2014
                 *
                 */
                if ($(":submit[value='Turn editing on']").length || $("a:contains('Turn editing on')").length || $("button:contains('Turn editing on')").length) {
                    available_shortcuts.push(register_shortcut("pencil", "[17,18,69]", "E [Editing mode on]"));
                } else if ($(":submit[value='Turn editing off']").length || $("a:contains('Turn editing off')").length || $("button:contains('Turn editing off')").length) {
                    available_shortcuts.push(register_shortcut("pencil", "[17,18,69]", "E [Editing mode off]"));
                } else if ($(".editmode-switch-form").length) {
                    available_shortcuts.push(register_shortcut("pencil", "[17,18,69]", "E [Toggle Edit mode]"));
                } else if ($("button:contains('Customi')").length) {
                    available_shortcuts.push(register_shortcut("pencil", "[17,18,69]", "E [" + $("button:contains('Customi')").text() + "]"));
                } else if ($("button:contains('Stop customi')").length) {
                    available_shortcuts.push(register_shortcut("pencil", "[17,18,69]", "E [" + $("button:contains('Stop customi')").text() + "]"));
                }

                /**
                 *
                 * Sets the CTRL ALT L as a special shortcut
                 * to run link checker.
                 *
                 */
                if (available_shortcuts.length) { // If editing can be on/off means this user is a teacher.
                    available_shortcuts.push(register_shortcut("unlink", "[17,18,76]", "L [Run Link Check]"));
                }


                /**
                 *
                 * Get Help email shortcut. (unshift makes it the first in the list)
                 *
                 */
                available_shortcuts.unshift(register_shortcut("triangle-exclamation", "[17,18,72]", "H [Get Help from L&T]"));

                /**
                 *
                 * Student View CTRL + ALT + S
                 * Mar. 30th, 2019
                 *
                 */
                if ($("a.dropdown-item:contains('Switch role to...')").length > 0 || $("a.dropdown-item:contains('Return to my normal role')").length > 0) {
                    available_shortcuts.push(register_shortcut("user-secret", "[17,18,83]", "S [Toggle Student View]"));
                }

                /**
                 *
                 * Musichelper CTRL + ALT + N
                 * Mar. 3rd, 2017
                 *
                 */
                if ($('*[data-fieldtype="editor"]').is(":visible") || $('.qtype_essay_response').length > 0 || $('.answer input').length > 0) {
                    available_shortcuts.push(register_shortcut("music", "[17,18,78]", "N [Toggle Music Notes]"));
                }

                /**
                 *
                 * Mathhelper CTRL + ALT + M
                 * Mar. 3rd, 2017
                 *
                 */
                if ($('*[data-fieldtype="editor"]').is(":visible") || $('.qtype_essay_response').length > 0 || $('.answer input').length > 0) {
                    available_shortcuts.push(register_shortcut("calculator", "[17,18,77]", "M [Toggle Math Unicode]"));
                }

                /**
                 *
                 * Diacritical CTRL + ALT + D
                 * Sept. 22nd, 2020
                 *
                 */
                if ($('*[data-fieldtype="editor"]').is(":visible") || $('.qtype_essay_response').length > 0 || $('.answer input').length > 0) {
                    available_shortcuts.push(register_shortcut("language", "[17,18,68]", "D [Toggle Diacritical Unicode]"));
                }

                /**
                 *
                 * Printable Quiz CTRL + ALT + P
                 * Mar. 3rd, 2017
                 *
                 */
                if ($(".notyetanswered").length > 0) {
                    available_shortcuts.push(register_shortcut("print", "[17,18,80]", "P [Toggle Printable Quiz]"));
                }

                /**
                 *
                 * Make hyperlinks more accessible.
                 * May 4th, 2018
                 *
                 */
                if ($("#region-main a:not(a:has(img)), .block_html a:not(a:has(img))").not(".moodle-actionmenu a, .disabled a, a[class*='nav'], .btn, .section-navigation a, footer a, .breadcrumb a, .tree_item a, a[id*='label'], a:has(i), .tree_item").length > 0) {
                    available_shortcuts.push(register_shortcut("universal-access", "[17,18,66]", "B [Toggle Bold Links]"));
                }

                /**
                 *
                 * Toggle AltFormat icon.
                 * August 29th, 2022
                 *
                 */
                if ($(".local-bfaltformat-access-button").length > 0) {
                    if (Cookies.get("rhhidebrickfieldicon") != null) {
                        available_shortcuts.push(register_shortcut("retweet", "[17,18,67]", "C [Show AltFormat Icons]"));
                    } else {
                        available_shortcuts.push(register_shortcut("retweet", "[17,18,67]", "C [Hide AltFormat Icons]"));
                    }

                }

                /**
                 *
                 * Combine all shortcuts into one popup menu.
                 *
                 */
                var list = "";
                if (available_shortcuts.length > 0) {
                    available_shortcuts.forEach(function(element) {
                        if (Object.entries(element).length) {
                            list += '<div class="available_shortcuts_items" onclick="rosekeycode(' + element["codes"] + ', this);"><a class="btn btn-primary" href="javascript:void(0);">' + element["title"] + '</a></div>';
                        }
                    });
                    $("footer").before("<div id='available_shortcuts_popup'><div id='available_shortcuts_tab'>Shortcuts</div><div id='available_shortcuts_inner'>" + list + "</div></div>");
                }
            }, 1500);

            /**
             *
             * Make hyperlinks more accessible.
             * May 4th, 2018
             *
             */
            if (Cookies.get("rhlinknotice") != null) {
                togglerhlinknotice();
            }

            /**
             *
             * Toggle AltFormat icon
             * August 29th, 2022
             *
             */
            setInterval(function() {
                if (Cookies.get("rhhidebrickfieldicon") != null) {
                    $(".local-bfaltformat-access-button").parent("span").hide();
                }
            }, 1000);

        });
    });
    /**
     *
     *  END of Add a shortcut helper
     *
     */

    var params = $(location).attr('href').indexOf('?') > 0 ? $(location).attr('href').split('?')[1] : "";
    var pageediting = /notifyeditingon/; // Page editing mode
    var gradebookediting = /edit=1/; // Gradebook editing mode

    // Strip all parameters off the href so that the pages match more often.
    var newlocation = $(location).attr('href').indexOf('?') > 0 ? $(location).attr('href').substring(0, $(location).attr('href').indexOf('?')) : $(location).attr('href');

    // If scroll AND location cookie is set and the location is the same, scroll to the position saved in the scroll cookie.
    if (Cookies.length) {
        if (Cookies.get("scroll") !== null && Cookies.get("oldlocation") !== null && Cookies.get("oldlocation") == newlocation) {
            var multiplier = 1; // Multiplier
            if (params.search(pageediting) != -1) { // Page editing mode
                var scrollextras = {
                    x: .12,
                    f: .0135
                }; // 12% page length difference in editing mode and .0135 fudge factor
                multiplier = multiplier + scrollextras['x'];
                Cookies.set("scrollextras", scrollextras);
            } else if (params.search(gradebookediting) != -1) { // Gradebook editing mode
                var scrollextras = {
                    x: .02,
                    f: 0
                }; // 2% page length difference in editing mode and 0 fudge factor
                multiplier = multiplier + scrollextras['x'];
                Cookies.set("scrollextras", scrollextras);
            } else if (Cookies.get("scrollextras") !== undefined) { // Going back to non editing mode
                var scrollextras = JSON.parse(Cookies.get("scrollextras"));
                multiplier = multiplier - scrollextras['x'] + scrollextras['f'];
            }

            $(document).scrollTop(Cookies.get("scroll") * multiplier); // Set the new scroll position
        } else {
            Cookies.remove("oldlocation");
            Cookies.remove("scroll");
            Cookies.remove("scrollextras");
        }
    }

    $(window).on("unload", function(e) {
        // Strip all parameters off the href so that the pages match more often.
        var newlocation = $(location).attr('href').indexOf('?') > 0 ? $(location).attr('href').substring(0, $(location).attr('href').indexOf('?')) : $(location).attr('href');

        Cookies.set("scroll", $(document).scrollTop()); // Set a cookie that holds the scroll position.
        Cookies.set("oldlocation", newlocation); // Set a cookie that holds the current location.
    });

    /**
     *
     *  END OF KEYBOARD SHORTCUT
     *
     */

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     *
     * My Colorful Portrait questionnaire auto calculator
     * requested by Matthew Lovell on July 16th, 2015
     *
     */
    $(function() {
        var surveyTitle = $(".surveyTitle").html();
        if (surveyTitle == "My Colorful Portrait") {
            setInterval(
                function(fieldsets) {
                    var colorGroups = {
                        "orange": 0,
                        "gold": 0,
                        "blue": 0,
                        "green": 0
                    };
                    var questionNumber = 1;
                    $.each(fieldsets, function() {
                        if (questionNumber == 1) {
                            var raterows = $(this).find("tr.raterow");
                            var rowNumber = 1;
                            $.each(raterows, function() {
                                var selected_value = $(this).find('input:checked').val();
                                if (selected_value >= 0) {
                                    selected_value++; //because it starts at 0, we need to add 1 to make the 1st option count as 1
                                    if (rowNumber == 1) {
                                        colorGroups.orange += selected_value;
                                    } else if (rowNumber == 2) {
                                        colorGroups.gold += selected_value;
                                    } else if (rowNumber == 3) {
                                        colorGroups.blue += selected_value;
                                    } else if (rowNumber == 4) {
                                        colorGroups.green += selected_value;
                                    }
                                }
                                rowNumber++;
                            });
                        } else if (questionNumber == 2) {
                            var raterows = $(this).find("tr.raterow");
                            var rowNumber = 1;
                            $.each(raterows, function() {
                                var selected_value = $(this).find('input:checked').val();
                                if (selected_value >= 0) {
                                    selected_value++; //because it starts at 0, we need to add 1 to make the 1st option count as 1
                                    if (rowNumber == 4) {
                                        colorGroups.orange += selected_value;
                                    } else if (rowNumber == 3) {
                                        colorGroups.gold += selected_value;
                                    } else if (rowNumber == 2) {
                                        colorGroups.blue += selected_value;
                                    } else if (rowNumber == 1) {
                                        colorGroups.green += selected_value;
                                    }
                                }
                                rowNumber++;
                            });
                        } else if (questionNumber == 3) {
                            var raterows = $(this).find("tr.raterow");
                            var rowNumber = 1;
                            $.each(raterows, function() {
                                var selected_value = $(this).find('input:checked').val();
                                if (selected_value >= 0) {
                                    selected_value++; //because it starts at 0, we need to add 1 to make the 1st option count as 1
                                    if (rowNumber == 3) {
                                        colorGroups.orange += selected_value;
                                    } else if (rowNumber == 1) {
                                        colorGroups.gold += selected_value;
                                    } else if (rowNumber == 2) {
                                        colorGroups.blue += selected_value;
                                    } else if (rowNumber == 4) {
                                        colorGroups.green += selected_value;
                                    }
                                }
                                rowNumber++;
                            });
                        } else if (questionNumber == 4) {
                            var raterows = $(this).find("tr.raterow");
                            var rowNumber = 1;
                            $.each(raterows, function() {
                                var selected_value = $(this).find('input:checked').val();
                                if (selected_value >= 0) {
                                    selected_value++; //because it starts at 0, we need to add 1 to make the 1st option count as 1
                                    if (rowNumber == 2) {
                                        colorGroups.orange += selected_value;
                                    } else if (rowNumber == 1) {
                                        colorGroups.gold += selected_value;
                                    } else if (rowNumber == 3) {
                                        colorGroups.blue += selected_value;
                                    } else if (rowNumber == 4) {
                                        colorGroups.green += selected_value;
                                    }
                                }
                                rowNumber++;
                            });
                        } else if (questionNumber == 5) {
                            var raterows = $(this).find("tr.raterow");
                            var rowNumber = 1;
                            $.each(raterows, function() {
                                var selected_value = $(this).find('input:checked').val();
                                if (selected_value >= 0) {
                                    selected_value++; //because it starts at 0, we need to add 1 to make the 1st option count as 1
                                    if (rowNumber == 3) {
                                        colorGroups.orange += selected_value;
                                    } else if (rowNumber == 4) {
                                        colorGroups.gold += selected_value;
                                    } else if (rowNumber == 2) {
                                        colorGroups.blue += selected_value;
                                    } else if (rowNumber == 1) {
                                        colorGroups.green += selected_value;
                                    }
                                }
                                rowNumber++;
                            });
                        } else if (questionNumber == 6) {
                            $(this).find("input").val(colorGroups.orange);
                        } else if (questionNumber == 7) {
                            $(this).find("input").val(colorGroups.gold);
                        } else if (questionNumber == 8) {
                            $(this).find("input").val(colorGroups.blue);
                        } else if (questionNumber == 9) {
                            $(this).find("input").val(colorGroups.green);
                        }

                        if ((colorGroups.orange + colorGroups.gold + colorGroups.blue + colorGroups.green) >= 10) {
                            var sortable = [];
                            var counter = 1;
                            for (var color in colorGroups) {
                                sortable.push([color, colorGroups[color], counter]);
                                counter++;
                            }
                            sortable.sort(function(a, b) {
                                return b[1] - a[1]
                            });

                            if (questionNumber == 10) {
                                $(this).find("select :nth-child(" + (sortable[0][2] + 1) + ")").prop('selected', true);
                            } else if (questionNumber == 11) {
                                $(this).find("select :nth-child(" + (sortable[1][2] + 1) + ")").prop('selected', true);
                            } else if (questionNumber == 12) {
                                $(this).find("select :nth-child(" + (sortable[2][2] + 1) + ")").prop('selected', true);
                            } else if (questionNumber == 13) {
                                $(this).find("select :nth-child(" + (sortable[3][2] + 1) + ")").prop('selected', true);
                            }
                        }
                        questionNumber++;
                    });
                }, 1000, $(".surveyTitle").closest("form").children("fieldset"));
        }
    });
    /**
     *
     *  END of Colorful Portrait auto calculator
     *
     */

    /**
     *
     * Questionnaire rate question randomizer
     * requested by Ella Ingram on Oct. 6th, 2016
     *
     */
    $(function() {
        if ($("#phpesp_response").length && $(".quest_rate_randomizer").length) {
            var rows = $(".qn-answer table tr.raterow");
            for (var i = rows.length - 1; i >= 0; i--) {
                var j = Math.floor(Math.random() * rows.length);
                rows.eq(i).after(rows[j]);
            }
        }
    });

    /**
     *
     * Questionnaire question randomizer
     * requested by Anthony Ribera on Nov. 3rd, 2016
     * Questionnaire checkbox randomizer
     * requested by Anthony Ribera on May 14th, 2017
     *
     */
    $(function() {
        if ($("#phpesp_response").length && $(".quest_randomizer").length) {
            var rows = $(".qn-container");
            var first = parseInt($(".qn-number").first().text());

            for (var i = rows.length - 1; i >= 0; i--) {
                var j = Math.floor(Math.random() * rows.length);
                rows.eq(i).after(rows[j]);
            }
            var rows = $(".qn-number"); // re-number questions
            if (rows.length) { // if numbers are shown
                for (var i = 0; i < rows.length; i++) {
                    rows.eq(i).text(first + i);
                }
            }

        }

        if ($(".qn-answer input[type='checkbox']").length && $(".quest_randomizer").length) {
            var inputs = $(".qn-answer input[type='checkbox']");
            for (var i = inputs.length - 1; i >= 0; i--) {
                var j = i;
                do {
                    j = Math.floor(Math.random() * inputs.length);
                }
                while (j == i);

                inputs.eq(i).before($(inputs[j]).nextAll().addBack().slice(0, 3));
            }
        }
    });
    /**
     *
     *  END of Questionnaire randomizer
     *
     */


    /**
     *
     * Reset Scroll Position
     * Dec. 15th, 2017
     * requested by Eric Reyes
     *
     */
    $(function() {
        var url = window.location.href; // Returns full URL
        if (url.search("mod/quiz/report.php") > 0 && url.search("&mode=grading") > 0) {
            Cookies.remove("scroll");
            $(document).scrollTop(0);
        }
    });
    /**
     *
     *  END of Reset Scroll Position
     *
     */


    /**
     *
     * Add autolink class to external links
     * July 27th, 2018
     *
     */
    $(function() {
        $(document).ready(function() {
            $("a:not(a:has(img))").not(
                ".moodle-actionmenu a, .disabled a, a[class*='nav'], .btn, .section-navigation a, footer a, .breadcrumb a, .tree_item a, a[id*='label'], a:has(i), .tree_item"
            ).each(function() {
                if ($(this).prev().find('img').length == 0 &&
                    $(this).prev().find('i').length == 0 &&
                    $(this).text().length > 0 &&
                    !$(this).find(".card-img-top").length) {
                    if (this.hostname && this.hostname !== location.hostname) {
                        $(this).addClass("autolink notreally");
                    }
                } else {
                    if ($(this).prev()[0] !== undefined &&
                        $(this).prev()[0].nextSibling !== undefined &&
                        $(this).prev()[0].nextSibling.length > 3) {
                        if (this.hostname && this.hostname !== location.hostname) {
                            $(this).toggleClass("autolink notreally");
                        }
                    }
                }
            });
        });
    });
    /**
     *
     *  End of Add autolink class to external links
     *
     */

    /**
     *
     * Feedback boxes in Singleview report change to textareas on click and convert back.
     * Feb 25th, 2019
     * requested by Claude Anderson
     */
    $(function() {
        var myFunction = function() {
            var el = this;
            var type = el.nodeName.toLowerCase();
            var rpl = document.createElement(type === 'input' ? 'textarea' : 'input');
            var attributes = $(this).prop("attributes");

            // loop through <select> attributes and apply them on <div>
            $.each(attributes, function() {
                $(rpl).attr(this.name, this.value);
            });

            rpl.rows = 5;
            el.parentNode.replaceChild(rpl, el);
            $(rpl).val(type === 'input' ? $(el).val().replace(/ {5}/g, "\r\n") : $(el).val().replace(/(\r\n|\n|\r)/gm, '     '));
            if (type == 'input') {
                $(rpl).focus();
            }
        };

        $(document).on('focus', 'input[name*="feedback"]input[type=text]', myFunction);
        $(document).on('blur', 'textarea[name*="feedback"]', myFunction);
    });
    /**
     *
     *  End of Feedback boxes in Singleview report change to textareas on click and convert back.
     *
     */

    /**
     *
     * Save comments automatically in assignment grader.
     * May 8th, 2019
     * requested by Ella Ingram
     */
    $(function() {
        var myFunction = function() {
            $("a[id^=comment-action-post]")[0].click(); // Click the "Save comment" link
        };

        $("form[data-region='grading-actions-form']").on('click', 'button[name^=save]', myFunction); // Both save button events
    });
    /**
     *
     *  End of Feedback boxes in Singleview report change to textareas on click and convert back.
     *
     */

    /**
     *
     * Remove core toolkit link.
     * January 12th, 2022
     */
    $(function() {
        if ($('.dropdown-menu .fa-area-chart').closest('div').length > 1) {
            $('.dropdown-menu .fa-area-chart').closest('div')[1].remove(); // toolkit remove
        }
    });
    /**
     *
     *  End of Remove core toolkit link.
     *
     */

    /**
     *
     * BSGRID fix.
     * January 12th, 2022
     */
    $(function() {
        $(".row-fluid").addClass("row"); // bsgrid fix
    });
    /**
     *
     *  End of BSGRID fix.
     *
     */

    /**
     *
     * scrollbar fix.
     * January 12th, 2022
     */
    $(function() {
        $('.no-overflow > .no-overflow').css("overflow", "hidden"); // scrollbar fix
    });
    /**
     *
     *  End of scrollbar fix.
     *
     */

    /**
     *
     * RHIT variables.
     * January 12th, 2022
     */
    $(function() {
        $('.rhvariable').each(function(index) { //rhit variable
            var n = $(this).attr("name");
            var v = $(this).val();
            $("a[href").each(function() {
                if (this.href.indexOf('#') > -1) {
                    this.href = this.href.replace("#" + n, v);
                }
            });
        });
    });
    /**
     *
     *  End of RHIT variables.
     *
     */

    /**
     *
     * Login page changes.
     * January 12th, 2022
     */
    $(function() {
        if ($('#page-login-index').length) {
            $('.login-divider:last').insertBefore($('.login-form'));
            $('.login-identityproviders').insertBefore($('.login-divider:first'));
            $('.login-identityprovider-btn').addClass('btn-primary');
            $('.login-form').wrap('<div><div class="rosemanualtoggle" style="display:none"></div></div>');
            $('.rosemanualtoggle').before('<button class="rosemanualtoggle btn btn-block btn-secondary" onclick="jQuery(\'.rosemanualtoggle\').toggle()">Guest Login Form</button>');
        }
    });
    /**
     *
     *  End of Login page changes.
     *
     */



    /**
     *
     * Combined video fixer
     * Aug. 9th, 2022
     *
     * Embedded video can have an issue where the type is set to "flash" and the file doesn't play
     * Also, the video is not flexible in size
     * Add a flexible wrapper and class to video embeds
     *
     */
    $(function() {
        function createflexiblevideos() {
            $("iframe, embed, object").not('.sketch_iframe').not("div[data-fieldtype='editor'] iframe", "div[data-fieldtype='editor'] embed", "div[data-fieldtype='editor'] object").not(".panopto-flexible-iframe").each(function(index, value) {
                let url = value.src !== undefined ? value.src : value.data !== undefined ? value.data : false;
                if (url !== false) {
                    // if youtube embed.
                    let youtube = (url.indexOf("youtube") > 0 || url.indexOf("youtu.be") > 0) ? true : false;
                    // Panopto TinyMCE LTI. or Panopto Atto LTI. or old embed.
                    let panopto = (url.indexOf("/lib/editor/tiny/plugins/panoptoltibutton/view") > 0 || url.indexOf("/lib/editor/atto/plugins/panoptoltibutton/view") > 0 || url.indexOf("panopto.com/Panopto/Pages/Embed") > 0) ? true : false;

                    // Find all embeds that have type "flash"
                    if (value.type !== undefined && value.type.indexOf("flash") > 0) {
                        if (youtube || panopto) {
                            // remove type to fix flash embed issues.
                            $(this).removeAttr("type");
                            // copy the embed to reinitialize.
                            let clone = $(this).clone();
                            $(this).after(clone);
                            // panopto has an image this is not needed.
                            if (panopto) {
                                $(this).parent().prev().find("img").parent().remove();
                            }
                            // remove the old embed.
                            $(this).remove();
                        }
                    }

                    // Add flexible wrapper and class to video embeds.
                    if (youtube || panopto) {
                        $(this).css({
                            'width': "",
                            'height': ""
                        }); // Unset width and height styles.
                        $(this).removeAttr("width height"); // Unset width and height attributes.
                        $(this).addClass("panopto-flexible-iframe"); // Give iframe our custom class.
                        $(this).wrap('<div class="panopto-flexible-container">'); // Add wrapper.
                    }
                }
            })
        }
        // Run once and then every 2 seconds.
        createflexiblevideos();
        setInterval(createflexiblevideos, 2000);
    });
    /**
     *
     *  END of Combined video fixer
     *
     */


    /**
     *
     * Fix embedded pdf's that get type set to application/x-shockwave-flash.
     * July 28th, 2022
     * requested by Danny Tette-Richtor
     *
     */
    $(function() {
        $('iframe, embed, object').not('.sketch_iframe').not("div[data-fieldtype='editor'] iframe", "div[data-fieldtype='editor'] embed", "div[data-fieldtype='editor'] object").each(function(index, value) {
            let url = value.src === undefined ? value.src : "";
            url = url == "" ? value.data : url;
            if (url !== undefined && url.indexOf(".pdf") > 0) {
                var d = {};
                d.data = this.data;
                d.src = this.src;
                d.width = this.width;
                d.height = this.height;
                this.type = 'application/pdf';
                this.data = d.data;
                this.src = d.src;
                this.width = d.width > 0 ? d.width : '100%';
                this.height = d.height > 0 ? d.height : '500px';
            }
        });
    });
    /**
     *
     *  Fix embedded pdf's that get type set to application/x-shockwave-flash.
     *
     */

    /**
     *
     * 3d Viewer filter.
     * July 6th, 2024
     *
     */
    $(function() {
        $("a[href*='.']").filter(function() {
            return $(this).attr("href").match(/\.(glb|gltf)$/i);
        }).each(function() {
            let data = $(this).data();
            JSON.stringify(data);
            $(this).before('<iframe style="border:0;width:100%;height:600px" src="' + M.cfg.wwwroot + '/rh/3dviewer.php?file=' + encodeURIComponent($(this).attr("href")) + '&' + $.param(data) + '"></iframe>');
        });
    });
    /**
     *
     * 3d Viewer filter.
     * July 6th, 2024
     *
     */

    /**
     *
     * Custom toggles hide/display areas.
     * February 6th, 2023
     *
     */
    $(function() {
        $(".rh_toggler").each(function() {
            var r = $(this).data("rows");
            var myparent = $(this).closest("li.activity");
            var nextactivity = rh_toggler_get_next(myparent);
            $(myparent).nextUntil(nextactivity).wrapAll("<div class='rh_toggler_hide'></div>");
            $(this).addClass("fa");
            if ($('.editing').length) {
                $(this).addClass("fa-chevron-circle-down");
            } else {
                $(this).addClass("rh_toggler_hidden");
                $(this).addClass("fa-chevron-circle-right");
                $(myparent).next().hide();
            }

            $(this).click(function() {
                if ($(this).hasClass("rh_toggler_hidden")) {
                    $(this).removeClass("rh_toggler_hidden");
                    $(this).removeClass("fa-chevron-circle-right");
                    $(this).addClass("fa-chevron-circle-down");
                    $(myparent).next().show("slow");
                } else {
                    $(this).addClass("rh_toggler_hidden");
                    $(this).addClass("fa-chevron-circle-right");
                    $(this).removeClass("fa-chevron-circle-down");
                    $(myparent).next().hide("hide");
                }
            });
        });

        function rh_toggler_get_next(activity) {
            var nextactivity = $(activity).next("li");
            if ($(activity).find(".rh_toggler").length > 0) {
                if ($(activity).next(".rh_toggler_hide").length > 0) { // it is a div next so skip it all.
                    nextactivity = $(activity).next().next();
                } else {
                    var skip = $(activity).find(".rh_toggler").data("rows");
                    activity = $(activity).next("li");
                    var f = 1;
                    while (f <= skip) {
                        if ($(activity).find(".rh_toggler").length > 0) {
                            activity = rh_toggler_get_next($(activity));
                        } else {
                            activity = $(activity).next("li");
                        }
                        f++;
                    }
                    nextactivity = activity;
                }
            }
            return nextactivity;
        }
    });
    /**
     *
     *  Custom toggles hide/display areas.
     *
     */


    /**
     *
     * Tab key in grader report should move to next student instead of next rade item.
     * Dec 7th, 2023
     * requested by Jason Pflueger
     *
     */
    $(function() {
        var array = [];
        $('.gradereport-grader-table tr').each(function() {
            var obj = [];
            $(this).find('input.form-control').each(function(i) {
                obj[i] = $(this).attr('id');
            });

            if (obj.length) {
                array.push(obj);
            }
        });

        array.forEach(processrows);

        function processrows(row, rowk, array) {
            row.forEach(function(field, fieldk) {
                $("body").on('keydown', "#" + field, function(e) {
                    var keyCode = e.keyCode || e.which;
                    if (e.shiftKey && keyCode == 9) {
                        e.preventDefault();
                        if (typeof array[rowk - 1] !== "undefined") {
                            $("#" + array[rowk - 1][fieldk]).focus();
                        }
                    } else if (keyCode == 9) {
                        e.preventDefault();
                        if (typeof array[rowk + 1] !== "undefined") {
                            $("#" + array[rowk + 1][fieldk]).focus();
                        }
                    }
                });
            });
        }
    });
    /**
     *
     *  Tab key in grader report should move to next student instead of next rade item.
     *
     */

});
