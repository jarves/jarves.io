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

$(document).ready(function() {
    console.log('ready');
   $('pre > code').each(function(key, item) {
       var code = $('<div class="code-top"></div><div class="code-bottom"></div><div class="code-left"></div><div class="code-right"></div>');
       $(item).parent().append(code);
       $(item).parent().addClass('fancy');
   })
});