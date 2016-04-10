/*
 * This file is part of Jarves.
 *
 * (c) Marc J. Schmidt <marc@marcjschmidt.de>
 *
 *     J.A.R.V.E.S - Just A Rather Very Easy [content management] System.
 *
 *     http://jarves.io
 *
 * To get the full copyright and license information, please view the
 * LICENSE file, that was distributed with this source code.
 */

$(document).ready(function () {
    $('pre > code').each(function (key, item) {
        var code = $('<div class="code-top"></div><div class="code-bottom"></div><div class="code-left"></div><div class="code-right"></div>');
        $(item).parent().append(code);
        $(item).parent().addClass('fancy');
    });

    $('h1, h2, h3, h4, h5, h6', '.documentation-content-right').each(function (key, item) {
        var name = $(item).text().replace(/\s\s*/g, '_');
        var link = location.pathname + '#' + name;
        var linker = $('<a class="bookmarker" href="' + link + '" name="' + name + '"><i class="fa fa-link"></i></a>');
        $(item).prepend(linker);

        if (location.hash.substr(1) === name) {
            console.log(location.hash.substr(1), name, $(item).offset().top);
            setTimeout(function() {
                window.scrollTo(0, $(item).offset().top);
            }, 50);
        }
    });
});
