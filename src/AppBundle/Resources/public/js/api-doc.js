$(document).ready(function() {
    if (window.location.hash) {
        var hash = window.location.hash.substr(1)
        var element = document.getElementById(hash);
        if (element) {
            var elem = $(element);
            setTimeout(function () {
                $('body').scrollTop(elem.position().top);
            });
            elem.find('a.toggler').click();
        }
    }
});

$('.toggler').click(function() {
    $(this).next().slideToggle('fast');
});

var toggleButtonText = function($btn) {
    if ($btn.text() === 'Default') {
        $btn.text('Raw');
    } else {
        $btn.text('Default');
    }
};

var renderRawBody = function($container) {
    var rawData, $btn;

    rawData = $container.data('raw-response');
    $btn = $container.parents('.pane').find('.to-raw');

    $container.addClass('prettyprinted');
    $container.html($('<div/>').text(rawData).html());

    $btn.removeClass('to-raw');
    $btn.addClass('to-prettify');

    toggleButtonText($btn);
};

var renderPrettifiedBody = function($container) {
    var rawData, $btn;

    rawData = $container.data('raw-response');
    $btn = $container.parents('.pane').find('.to-prettify');

    $container.removeClass('prettyprinted');
    $container.html(prettifyResponse(rawData));
    prettyPrint && prettyPrint();

    $btn.removeClass('to-prettify');
    $btn.addClass('to-raw');

    toggleButtonText($btn);
};

var unflattenDict = function(body) {
    var found = true;
    while (found) {
        found = false;

        for (var key in body) {
            var okey;
            var value = body[key];
            var dictMatch = key.match(/^(.+)\[([^\]]+)\]$/);

            if (dictMatch) {
                found = true;
                okey = dictMatch[1];
                var subkey = dictMatch[2];
                body[okey] = body[okey] || {};
                body[okey][subkey] = value;
                delete body[key];
            } else {
                body[key] = value;
            }
        }
    }
    return body;
}

$('.tabs li').click(function() {
    var contentGroup = $(this).parents('.content');

    $('.pane.selected', contentGroup).removeClass('selected');
    $('.pane.' + $(this).data('pane'), contentGroup).addClass('selected');

    $('li', $(this).parent()).removeClass('selected');
    $(this).addClass('selected');
});

var prettifyResponse = function(text) {
    try {
        var data = typeof text === 'string' ? JSON.parse(text) : text;
        text = JSON.stringify(data, undefined, '  ');
    } catch (err) {
    }

    // HTML encode the result
    return $('<div>').text(text).html();
};

var displayFinalUrl = function(xhr, method, url, container) {
    container.text(method + ' ' + url);
};

var displayResponseData = function(xhr, container) {
    var data = xhr.responseText;

    container.data('raw-response', data);

    renderPrettifiedBody(container);

    container.parents('.pane').find('.to-prettify').text('Raw');
    container.parents('.pane').find('.to-raw').text('Raw');
};

var displayResponseHeaders = function(xhr, container) {
    var text = xhr.status + ' ' + xhr.statusText + "\n\n";
    text += xhr.getAllResponseHeaders();

    container.text(text);
};

var displayResponse = function(xhr, method, url, result_container) {
    displayFinalUrl(xhr, method, url, $('.url', result_container));
    displayResponseData(xhr, $('.response', result_container));
    displayResponseHeaders(xhr, $('.headers', result_container));

    result_container.show();
};

$('.pane.sandbox form').submit(function() {
    var url = $(this).attr('action'), method = $(this).attr('method'), self = this, params = {}, headers = {}, content = $(this).find('textarea.content').val(), result_container = $('.result', $(this).parent());

    if (method === 'ANY') {
        method = 'POST';
    } else if (method.indexOf('|') !== -1) {
        method = method.split('|').sort().pop();
    }

    // set default requestFormat
    var requestFormat = $('#request_format').val();
    var requestFormatMethod = '{{ requestFormatMethod }}';
    if (requestFormatMethod == 'format_param') {
        params['_format'] = requestFormat;
    } else if (requestFormatMethod == 'accept_header') {
        headers['Accept'] = 'application/' + requestFormat;
    }

    // set default bodyFormat
    var bodyFormat = $('#body_format').val();

    if (!('Content-type' in headers)) {
        headers['Content-type'] = 'application/' + bodyFormat;
    }

    // retrieve all the parameters to send
    $('.parameters .tuple', $(this)).each(function() {
        var key, value;

        key = $('.key', $(this)).val();
        value = $('.value', $(this)).val();

        if (value) {
            params[key] = value;
        }
    });

    // retrieve the additional headers to send
    $('.headers .tuple', $(this)).each(function() {
        var key, value;

        key = $('.key', $(this)).val();
        value = $('.value', $(this)).val();

        if (value) {
            headers[key] = value;
        }

    });

    // fix parameters in URL
    for (var key in $.extend({}, params)) {
        if (url.indexOf('{' + key + '}') !== -1) {
            url = url.replace('{' + key + '}', params[key]);
            delete params[key];
        }
    }
    ;

    // disable all the fiels and buttons
    $('input, button', $(this)).attr('disabled', 'disabled');

    // append the query authentication
    if (authentication_delivery == 'query') {
        url += url.indexOf('?') > 0 ? '&' : '?';
        url += api_key_parameter + '=' + $('#api_key').val();
    }

    // prepare the api enpoint
    var endpoint = ''; //todo, add here a ping pong endpoint

    if ($('#api_endpoint') && $('#api_endpoint').val() != null) {
        endpoint = $('#api_endpoint').val();
    }

    // prepare final parameters
    var body = {};
    if (bodyFormat == 'json' && method != 'GET') {
        body = unflattenDict(params);
        body = JSON.stringify(body);
    } else {
        body = params;
    }
    var data = content.length ? content : body;

    // and trigger the API call
    $.ajax({
        url: endpoint + url,
        type: method,
        data: data,
        headers: headers,
        crossDomain: true,
        beforeSend: function(xhr) {
            if (authentication_delivery == 'http_basic') {
                xhr.setRequestHeader('Authorization', 'Basic ' + btoa($('#api_key').val() + ':' + $('#api_pass').val()));
            } else if (authentication_delivery == 'container') {
                xhr.setRequestHeader(api_key_parameter, $('#api_key').val());
            }
        },
        complete: function(xhr) {
            displayResponse(xhr, method, url, result_container);

            // and enable them back
            $('input:not(.content-type), button', $(self)).removeAttr('disabled');
        }
    });

    return false;
});

$('.operations').delegate('.operation > a', 'click', function(e) {
    if (history.pushState) {
        history.pushState(null, null, $(this).attr('name'));
        e.preventDefault();
    }
});

$('.pane.sandbox').delegate('.to-raw', 'click', function(e) {
    renderRawBody($(this).parents('.pane').find('.response'));

    e.preventDefault();
});

$('.pane.sandbox').delegate('.to-prettify', 'click', function(e) {
    renderPrettifiedBody($(this).parents('.pane').find('.response'));

    e.preventDefault();
});

$('.pane.sandbox').delegate('.to-expand, .to-shrink', 'click', function(e) {
    var $headers = $(this).parents('.result').find('.headers');
    var $label = $(this).parents('.result').find('a.to-expand');

    if ($headers.hasClass('to-expand')) {
        $headers.removeClass('to-expand');
        $headers.addClass('to-shrink');
        $label.text('Shrink');
    } else {
        $headers.removeClass('to-shrink');
        $headers.addClass('to-expand');
        $label.text('Expand');
    }

    e.preventDefault();
});

$('.pane.sandbox').on('click', '.add', function() {
    var html = $(this).parents('.pane').find('.tuple_template').html();

    $(this).before(html);

    return false;
});

$('.pane.sandbox').on('click', '.remove', function() {
    $(this).parent().remove();
});

$('.pane.sandbox').on('click', '.set-content-type', function(e) {
    var html;
    var $element;
    var $headers = $(this).parents('form').find('.headers');
    var content_type = $(this).prev('input.value').val();

    e.preventDefault();

    if (content_type.length === 0) {
        return;
    }

    $headers.find('input.key').each(function() {
        if ($.trim($(this).val().toLowerCase()) === 'content-type') {
            $element = $(this).parents('p');
            return false;
        }
    });

    if (typeof $element === 'undefined') {
        html = $(this).parents('.pane').find('.tuple_template').html();

        $element = $headers.find('legend').after(html).next('p');
    }

    $element.find('input.key').val('Content-Type');
    $element.find('input.value').val(content_type);

});