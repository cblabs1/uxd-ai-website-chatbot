/**
 * AI Chatbot Utilities JavaScript
 *
 * @package AI_Website_Chatbot
 * @since 1.0.0
 */

(function(window, $) {
    'use strict';

    // Utility namespace
    window.AIChatbotUtils = {
        
        // Storage utilities
        storage: {
            set: function(key, value, expiry) {
                var item = {
                    value: value,
                    expiry: expiry ? Date.now() + expiry : null
                };
                localStorage.setItem('ai_chatbot_' + key, JSON.stringify(item));
            },

            get: function(key) {
                var itemStr = localStorage.getItem('ai_chatbot_' + key);
                if (!itemStr) {
                    return null;
                }

                var item = JSON.parse(itemStr);
                if (item.expiry && Date.now() > item.expiry) {
                    localStorage.removeItem('ai_chatbot_' + key);
                    return null;
                }

                return item.value;
            },

            remove: function(key) {
                localStorage.removeItem('ai_chatbot_' + key);
            },

            clear: function() {
                for (var key in localStorage) {
                    if (key.startsWith('ai_chatbot_')) {
                        localStorage.removeItem(key);
                    }
                }
            }
        },

        // Cookie utilities
        cookies: {
            set: function(name, value, days) {
                var expires = "";
                if (days) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = 'ai_chatbot_' + name + "=" + value + expires + "; path=/";
            },

            get: function(name) {
                var nameEQ = 'ai_chatbot_' + name + "=";
                var ca = document.cookie.split(';');
                for (var i = 0; i < ca.length; i++) {
                    var c = ca[i];
                    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                }
                return null;
            },

            remove: function(name) {
                document.cookie = 'ai_chatbot_' + name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
            }
        },

        // Text processing utilities
        text: {
            escapeHtml: function(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            },

            unescapeHtml: function(text) {
                var map = {
                    '&amp;': '&',
                    '&lt;': '<',
                    '&gt;': '>',
                    '&quot;': '"',
                    '&#039;': "'"
                };
                return text.replace(/&amp;|&lt;|&gt;|&quot;|&#039;/g, function(m) { return map[m]; });
            },

            truncate: function(text, length, suffix) {
                suffix = suffix || '...';
                if (text.length <= length) {
                    return text;
                }
                return text.substring(0, length - suffix.length) + suffix;
            },

            stripHtml: function(html) {
                var tmp = document.createElement("DIV");
                tmp.innerHTML = html;
                return tmp.textContent || tmp.innerText || "";
            },

            linkify: function(text) {
                var urlRegex = /(https?:\/\/[^\s]+)/g;
                return text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener">$1</a>');
            },

            highlight: function(text, term) {
                if (!term) return text;
                var regex = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                return text.replace(regex, '<mark>$1</mark>');
            }
        },

        // Date/time utilities
        time: {
            formatRelative: function(date) {
                var now = new Date();
                var diff = now.getTime() - date.getTime();
                var seconds = Math.floor(diff / 1000);
                var minutes = Math.floor(seconds / 60);
                var hours = Math.floor(minutes / 60);
                var days = Math.floor(hours / 24);

                if (seconds < 60) return 'just now';
                if (minutes < 60) return minutes + 'm ago';
                if (hours < 24) return hours + 'h ago';
                if (days < 7) return days + 'd ago';
                
                return date.toLocaleDateString();
            },

            formatTime: function(date) {
                return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            },

            formatDateTime: function(date) {
                return date.toLocaleDateString() + ' ' + this.formatTime(date);
            }
        },

        // Animation utilities
        animation: {
            fadeIn: function($element, duration, callback) {
                duration = duration || 300;
                $element.css('opacity', 0).show();
                $element.animate({opacity: 1}, duration, callback);
            },

            fadeOut: function($element, duration, callback) {
                duration = duration || 300;
                $element.animate({opacity: 0}, duration, function() {
                    $element.hide();
                    if (callback) callback();
                });
            },

            slideDown: function($element, duration, callback) {
                duration = duration || 300;
                $element.slideDown(duration, callback);
            },

            slideUp: function($element, duration, callback) {
                duration = duration || 300;
                $element.slideUp(duration, callback);
            },

            bounce: function($element) {
                $element.addClass('animated bounce');
                setTimeout(function() {
                    $element.removeClass('animated bounce');
                }, 1000);
            },

            shake: function($element) {
                $element.addClass('animated shake');
                setTimeout(function() {
                    $element.removeClass('animated shake');
                }, 1000);
            }
        },

        // Performance utilities
        performance: {
            debounce: function(func, wait) {
                var timeout;
                return function() {
                    var context = this, args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(function() {
                        func.apply(context, args);
                    }, wait);
                };
            },

            throttle: function(func, limit) {
                var inThrottle;
                return function() {
                    var args = arguments;
                    var context = this;
                    if (!inThrottle) {
                        func.apply(context, args);
                        inThrottle = true;
                        setTimeout(function() {
                            inThrottle = false;
                        }, limit);
                    }
                };
            }
        },

        // Browser detection utilities
        browser: {
            isMobile: function() {
                return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            },

            isTablet: function() {
                return /iPad|Android|Tablet/i.test(navigator.userAgent) && !this.isMobile();
            },

            isDesktop: function() {
                return !this.isMobile() && !this.isTablet();
            },

            supportsLocalStorage: function() {
                try {
                    localStorage.setItem('test', 'test');
                    localStorage.removeItem('test');
                    return true;
                } catch (e) {
                    return false;
                }
            },

            supportsWebRTC: function() {
                return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
            }
        },

        // Sound utilities
        sound: {
            play: function(url, volume) {
                if (!url) return;
                
                var audio = new Audio(url);
                audio.volume = volume || 0.5;
                audio.play().catch(function(error) {
                    console.warn('Could not play sound:', error);
                });
            },

            playNotification: function() {
                // Use Web Audio API for notification sound
                if (window.AudioContext || window.webkitAudioContext) {
                    var context = new (window.AudioContext || window.webkitAudioContext)();
                    var oscillator = context.createOscillator();
                    var gainNode = context.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(context.destination);
                    
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    
                    gainNode.gain.setValueAtTime(0.3, context.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.5);
                    
                    oscillator.start(context.currentTime);
                    oscillator.stop(context.currentTime + 0.5);
                }
            }
        },

        // Network utilities
        network: {
            isOnline: function() {
                return navigator.onLine;
            },

            onStatusChange: function(callback) {
                window.addEventListener('online', function() {
                    callback(true);
                });
                
                window.addEventListener('offline', function() {
                    callback(false);
                });
            },

            ping: function(url, callback) {
                var start = Date.now();
                var img = new Image();
                
                img.onload = function() {
                    callback(null, Date.now() - start);
                };
                
                img.onerror = function() {
                    callback(new Error('Network error'), null);
                };
                
                img.src = url + '?cache=' + Math.random();
            }
        },

        // URL utilities
        url: {
            getParam: function(name) {
                var urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(name);
            },

            setParam: function(name, value) {
                var url = new URL(window.location);
                url.searchParams.set(name, value);
                window.history.pushState({}, '', url);
            },

            removeParam: function(name) {
                var url = new URL(window.location);
                url.searchParams.delete(name);
                window.history.pushState({}, '', url);
            },

            isValidUrl: function(string) {
                try {
                    new URL(string);
                    return true;
                } catch (_) {
                    return false;
                }
            }
        },

        // Validation utilities
        validation: {
            isEmail: function(email) {
                var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return regex.test(email);
            },

            isPhoneNumber: function(phone) {
                var regex = /^[\+]?[1-9][\d]{0,15}$/;
                return regex.test(phone.replace(/\s/g, ''));
            },

            isUrl: function(url) {
                var regex = /^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)$/;
                return regex.test(url);
            },

            hasSpecialChars: function(str) {
                var regex = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/;
                return regex.test(str);
            }
        },

        // Accessibility utilities
        accessibility: {
            announceToScreenReader: function(message) {
                var announcement = document.createElement('div');
                announcement.setAttribute('aria-live', 'polite');
                announcement.setAttribute('aria-atomic', 'true');
                announcement.style.position = 'absolute';
                announcement.style.left = '-10000px';
                announcement.style.width = '1px';
                announcement.style.height = '1px';
                announcement.style.overflow = 'hidden';
                
                document.body.appendChild(announcement);
                announcement.textContent = message;
                
                setTimeout(function() {
                    document.body.removeChild(announcement);
                }, 1000);
            },

            trapFocus: function($container) {
                var focusableElements = $container.find('a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select');
                var firstFocusable = focusableElements.first();
                var lastFocusable = focusableElements.last();
                
                $container.on('keydown', function(e) {
                    if (e.key === 'Tab') {
                        if (e.shiftKey) {
                            if (document.activeElement === firstFocusable[0]) {
                                lastFocusable.focus();
                                e.preventDefault();
                            }
                        } else {
                            if (document.activeElement === lastFocusable[0]) {
                                firstFocusable.focus();
                                e.preventDefault();
                            }
                        }
                    }
                });
                
                firstFocusable.focus();
            }
        },

        // File utilities
        file: {
            getFileExtension: function(filename) {
                return filename.split('.').pop().toLowerCase();
            },

            formatFileSize: function(bytes) {
                if (bytes === 0) return '0 Bytes';
                
                var k = 1024;
                var sizes = ['Bytes', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            },

            isImageFile: function(filename) {
                var imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
                var extension = this.getFileExtension(filename);
                return imageExtensions.includes(extension);
            },

            readFileAsText: function(file, callback) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    callback(null, e.target.result);
                };
                reader.onerror = function() {
                    callback(new Error('Failed to read file'), null);
                };
                reader.readAsText(file);
            }
        },

        // Math utilities
        math: {
            randomBetween: function(min, max) {
                return Math.floor(Math.random() * (max - min + 1)) + min;
            },

            roundToDecimals: function(num, decimals) {
                return Math.round(num * Math.pow(10, decimals)) / Math.pow(10, decimals);
            },

            clamp: function(num, min, max) {
                return Math.min(Math.max(num, min), max);
            },

            lerp: function(start, end, progress) {
                return start + (end - start) * progress;
            }
        },

        // Color utilities
        color: {
            hexToRgb: function(hex) {
                var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                return result ? {
                    r: parseInt(result[1], 16),
                    g: parseInt(result[2], 16),
                    b: parseInt(result[3], 16)
                } : null;
            },

            rgbToHex: function(r, g, b) {
                return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
            },

            lighten: function(hex, percent) {
                var rgb = this.hexToRgb(hex);
                if (!rgb) return hex;
                
                rgb.r = Math.min(255, Math.floor(rgb.r + (255 - rgb.r) * percent / 100));
                rgb.g = Math.min(255, Math.floor(rgb.g + (255 - rgb.g) * percent / 100));
                rgb.b = Math.min(255, Math.floor(rgb.b + (255 - rgb.b) * percent / 100));
                
                return this.rgbToHex(rgb.r, rgb.g, rgb.b);
            },

            darken: function(hex, percent) {
                var rgb = this.hexToRgb(hex);
                if (!rgb) return hex;
                
                rgb.r = Math.max(0, Math.floor(rgb.r * (100 - percent) / 100));
                rgb.g = Math.max(0, Math.floor(rgb.g * (100 - percent) / 100));
                rgb.b = Math.max(0, Math.floor(rgb.b * (100 - percent) / 100));
                
                return this.rgbToHex(rgb.r, rgb.g, rgb.b);
            }
        },

        // DOM utilities
        dom: {
            ready: function(callback) {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', callback);
                } else {
                    callback();
                }
            },

            createElement: function(tag, attributes, content) {
                var element = document.createElement(tag);
                
                if (attributes) {
                    for (var attr in attributes) {
                        element.setAttribute(attr, attributes[attr]);
                    }
                }
                
                if (content) {
                    element.textContent = content;
                }
                
                return element;
            },

            isInViewport: function(element) {
                var rect = element.getBoundingClientRect();
                return (
                    rect.top >= 0 &&
                    rect.left >= 0 &&
                    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                );
            }
        }
    };

})(window, jQuery);
