/**
 * OpenAI Chatbot Frontend JavaScript
 * 
 * This file manages the frontend chat interface, handles message sending,
 * conversation storage, and UI interactions with multiple response support
 */

(function($) {
    'use strict';
    
    /**
     * Chatbot state management
     */
    const Chatbot = {
        // State variables
        state: {
            isOpen: false,
            conversationId: '',
            messages: [],
            isWaitingForResponse: false,
            humanTakeover: false,
            newMessage: false
        },
        
        // DOM element cache
        elements: {
            $chatbot: null,
            $chatButton: null,
            $chatWindow: null,
            $chatMessages: null,
            $chatClose: null,
            $chatForm: null,
            $chatInput: null,
            $chatSubmit: null,
            $clearChat: null
        },
        
        /**
         * Initialize the chatbot
         */
        init: function() {
            // Check if chat is enabled
            if (openaiChatbot.chatEnabled === 'no') {
                return;
            }
            
            // Cache DOM elements
            this.cacheElements();
            
            // Add welcome message
            this.addWelcomeMessage();
            
            // Load conversation from local storage
            this.loadConversation();
            
            // Set up event listeners
            this.bindEvents();
            
            // Set up window resize handler
            this.setupResizeHandler();
            
            // Initial chat window height adjustment
            this.adjustChatWindowHeight();
            
            // Check for URL parameter to auto-open chat
            this.checkAutoOpen();
            
            console.log('OpenAI Chatbot initialized');
        },
        
        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.elements.$chatbot = $('#openai-chatbot');
            this.elements.$chatButton = $('.openai-chatbot-button');
            this.elements.$chatWindow = $('.openai-chatbot-window');
            this.elements.$chatMessages = $('.openai-chatbot-messages');
            this.elements.$chatClose = $('.openai-chatbot-close');
            this.elements.$chatForm = $('#openai-chatbot-form');
            this.elements.$chatInput = $('#openai-chatbot-message');
            this.elements.$chatSubmit = this.elements.$chatForm.find('button[type="submit"]');
            this.elements.$clearChat = $('.openai-chatbot-clear');
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Toggle chat window
            this.elements.$chatButton.on('click', this.toggleChat.bind(this));
            
            // Close chat window
            this.elements.$chatClose.on('click', this.closeChat.bind(this));
            
            // Send message
            this.elements.$chatForm.on('submit', this.handleMessageSubmit.bind(this));
            
            // Clear chat history
            this.elements.$clearChat.on('click', this.clearChat.bind(this));
            
            // Auto-resize textarea on input
            this.elements.$chatInput.on('input', this.autoResizeTextarea.bind(this));
            
            // Press Enter to send (but allow Shift+Enter for new line)
            this.elements.$chatInput.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.elements.$chatForm.submit();
                }
            }.bind(this));
            
            // Handle window unload to save conversation
            $(window).on('beforeunload', this.saveConversation.bind(this));
        },
        
        /**
         * Auto-resize textarea based on content
         */
        autoResizeTextarea: function() {
            const textarea = this.elements.$chatInput[0];
            
            // Reset height to allow shrinking
            textarea.style.height = 'auto';
            
            // Set new height based on content
            const newHeight = textarea.scrollHeight;
            textarea.style.height = (newHeight < 100) ? newHeight + 'px' : '100px';
            
            // Enable/disable send button based on input
            this.elements.$chatSubmit.prop('disabled', textarea.value.trim() === '');
        },
        
        /**
         * Set up window resize handler
         */
        setupResizeHandler: function() {
            let resizeTimer;
            
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(this.adjustChatWindowHeight.bind(this), 100);
            }.bind(this));
        },
        
        /**
         * Check for URL parameter to auto-open chat
         */
        checkAutoOpen: function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.get('openai_chat') === 'open') {
                setTimeout(function() {
                    this.openChat();
                }.bind(this), 1000);
            }
        },
        
        /**
         * Toggle chat window
         */
        toggleChat: function() {
            if (this.state.isOpen) {
                this.closeChat();
            } else {
                this.openChat();
            }
        },
        
        /**
         * Open chat window
         */
        openChat: function() {
            this.state.isOpen = true;
            this.elements.$chatbot.addClass('open');
            this.adjustChatWindowHeight();
            
            // Mark as read
            if (this.state.newMessage) {
                this.state.newMessage = false;
            }
            
            // Trigger event
            $(document).trigger('openai_chatbot_opened');
        },
        
        /**
         * Close chat window
         */
        closeChat: function() {
            this.state.isOpen = false;
            this.elements.$chatbot.removeClass('open');
            
            // Trigger event
            $(document).trigger('openai_chatbot_closed');
        },
        
        /**
         * Clear chat history
         */
        clearChat: function() {
            // Clear the chat messages
            this.elements.$chatMessages.empty();
            
            // Add welcome message back
            this.addWelcomeMessage();
            
            // Clear state
            this.state.messages = [];
            this.state.conversationId = '';
            this.state.humanTakeover = false;
            
            // Clear local storage
            localStorage.removeItem('openai_chatbot_conversation');
            
            // Trigger event
            $(document).trigger('openai_chatbot_cleared');
        },
        
        /**
         * Adjust chat window height for mobile
         */
        adjustChatWindowHeight: function() {
            if (window.innerWidth <= 480) {
                const windowHeight = window.innerHeight;
                const bottomOffset = 90; // Space for the chatbot button and margins
                const topOffset = 70;    // Space for the header
                
                this.elements.$chatWindow.css('height', (windowHeight - bottomOffset - topOffset) + 'px');
            } else {
                // Reset to default height on desktop
                this.elements.$chatWindow.css('height', '');
            }
            
            // Scroll to the bottom
            this.scrollToBottom();
        },
        
        /**
         * Add welcome message to chat
         */
        addWelcomeMessage: function() {
            const welcomeHTML = `
                <div class="openai-chatbot-welcome">
                    ${openaiChatbot.welcomeMessage}
                </div>
            `;
            
            this.elements.$chatMessages.append(welcomeHTML);
        },
        
        /**
         * Handle form submission
         */
        handleMessageSubmit: function(e) {
            e.preventDefault();
            
            const message = this.elements.$chatInput.val().trim();
            
            if (!message || this.state.isWaitingForResponse) {
                return;
            }
            
            // Send the message
            this.sendMessage(message);
        },
        
        /**
         * Send message to server
         */
        sendMessage: function(message) {
            // Add user message to UI
            this.addUserMessage(message);
            
            // Clear input
            this.elements.$chatInput.val('');
            
            // Reset textarea height
            this.elements.$chatInput.css('height', 'auto');
            
            // Disable submit button
            this.elements.$chatSubmit.prop('disabled', true);
            
            // Show loading indicator
            this.showLoadingIndicator();
            
            // Set waiting state
            this.state.isWaitingForResponse = true;
            
            // Send message to server
            $.ajax({
                url: openaiChatbot.ajaxurl,
                type: 'POST',
                data: {
                    action: 'openai_chatbot_send_message',
                    nonce: openaiChatbot.nonce,
                    message: message,
                    conversation_id: this.state.conversationId
                },
                success: this.handleSendMessageSuccess.bind(this),
                error: this.handleSendMessageError.bind(this)
            });
            
            // Trigger event
            $(document).trigger('openai_chatbot_message_sent', [message]);
        },
        
        /**
         * Handle successful message response with multiple response support
         */
        handleSendMessageSuccess: function(response) {
            // Remove loading indicator
            this.removeLoadingIndicator();
            
            // Reset input state
            this.elements.$chatSubmit.prop('disabled', false);
            
            if (response.success) {
                // Store conversation ID
                this.state.conversationId = response.data.conversation_id;
                
                // Handle different response scenarios
                if (response.data.status === 'waiting_for_human') {
                    this.state.humanTakeover = true;
                    this.addAssistantMessage(openaiChatbot.i18n.connectingAgent, true);
                } else if (response.data.status === 'error') {
                    this.addErrorMessage(response.data.error_message || openaiChatbot.i18n.errorMessage);
                } else {
                    // Add first response to UI
                    if (response.data.response) {
                        this.addAssistantMessage(response.data.response, response.data.is_human);
                        
                        // If we have additional responses, add them with a delay
                        if (response.data.is_multiple && response.data.additional_responses && response.data.additional_responses.length > 0) {
                            this.handleAdditionalResponses(response.data.additional_responses);
                        }
                    }
                }
                
                // Save conversation to local storage
                this.saveConversation();
                
                // Trigger event
                $(document).trigger('openai_chatbot_message_received', [response.data]);
            } else {
                this.addErrorMessage(response.data || openaiChatbot.i18n.errorMessage);
            }
            
            // Reset waiting state
            this.state.isWaitingForResponse = false;
        },
        
        /**
         * Handle additional responses with a natural typing delay
         */
        handleAdditionalResponses: function(additionalResponses) {
            if (!additionalResponses || !additionalResponses.length) return;
            
            // Process additional responses with a delay
            let delay = 1000; // Base delay
            
            additionalResponses.forEach((response, index) => {
                // Calculate a natural typing delay based on message length
                const typingDelay = delay + (Math.min(response.content.length * 20, 2000));
                
                setTimeout(() => {
                    // Show typing indicator before message
                    this.showLoadingIndicator();
                    
                    // Display message after a brief typing indicator
                    setTimeout(() => {
                        this.removeLoadingIndicator();
                        this.addAssistantMessage(response.content, false);
                        
                        // Add to state
                        this.state.messages.push({
                            role: 'assistant',
                            content: response.content,
                            time: new Date().toISOString(),
                            isHuman: false
                        });
                        
                        // Save after each additional message
                        this.saveConversation();
                    }, 1000 + Math.min(response.content.length * 5, 1500));
                }, typingDelay * (index + 1));
            });
        },
        
        /**
         * Handle message send error
         */
        handleSendMessageError: function() {
            // Remove loading indicator
            this.removeLoadingIndicator();
            
            // Add error message
            this.addErrorMessage(openaiChatbot.i18n.errorMessage);
            
            // Reset states
            this.state.isWaitingForResponse = false;
            this.elements.$chatSubmit.prop('disabled', false);
        },
        
        /**
         * Add user message to UI
         */
        addUserMessage: function(message) {
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            const messageHTML = `
                <div class="openai-chatbot-message openai-chatbot-message-user">
                    ${this.formatMessageText(message)}
                    <div class="openai-chatbot-message-time">${time}</div>
                </div>
            `;
            
            this.elements.$chatMessages.append(messageHTML);
            this.scrollToBottom();
            
            // Add to state
            this.state.messages.push({
                role: 'user',
                content: message,
                time: new Date().toISOString()
            });
        },
        
        /**
         * Add assistant message to UI
         */
        addAssistantMessage: function(message, isHuman = false) {
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const humanFlag = isHuman ? `<span class="openai-chatbot-human-flag">Human</span>` : '';
            
            // Format message text
            const formattedMessage = this.formatMessageText(message);
            
            const messageHTML = `
                <div class="openai-chatbot-message openai-chatbot-message-assistant">
                    ${formattedMessage}
                    <div class="openai-chatbot-message-time">
                        ${time} ${humanFlag}
                    </div>
                </div>
            `;
            
            this.elements.$chatMessages.append(messageHTML);
            this.scrollToBottom();
            
            // Add to state
            this.state.messages.push({
                role: 'assistant',
                content: message,
                time: new Date().toISOString(),
                isHuman: isHuman
            });
        },
        
        /**
         * Format message text - escape HTML and format links
         */
        formatMessageText: function(message) {
            if (!message) return '';
            
            // Escape HTML
            let safeMessage = this.escapeHTML(message);
            
            // Convert URLs to links
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            safeMessage = safeMessage.replace(urlRegex, function(url) {
                return `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`;
            });
            
            // Convert line breaks to <br>
            return safeMessage.replace(/\n/g, '<br>');
        },
        
        /**
         * Add error message to UI
         */
        addErrorMessage: function(message) {
            const messageHTML = `
                <div class="openai-chatbot-error">
                    ${this.escapeHTML(message)}
                </div>
            `;
            
            this.elements.$chatMessages.append(messageHTML);
            this.scrollToBottom();
        },
        
        /**
         * Show loading indicator
         */
        showLoadingIndicator: function() {
            const loadingHTML = `
                <div class="openai-chatbot-loading">
                    <div class="openai-chatbot-loading-dots">
                        <div class="openai-chatbot-loading-dot"></div>
                        <div class="openai-chatbot-loading-dot"></div>
                        <div class="openai-chatbot-loading-dot"></div>
                    </div>
                </div>
            `;
            
            this.elements.$chatMessages.append(loadingHTML);
            this.scrollToBottom();
        },
        
        /**
         * Remove loading indicator
         */
        removeLoadingIndicator: function() {
            $('.openai-chatbot-loading').remove();
        },
        
        /**
         * Scroll messages to bottom
         */
        scrollToBottom: function() {
            if (this.elements.$chatMessages.length) {
                this.elements.$chatMessages.scrollTop(this.elements.$chatMessages[0].scrollHeight);
            }
        },
        
        /**
         * Escape HTML special characters
         */
        escapeHTML: function(str) {
            if (!str) return '';
            
            const escapeMap = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return str.replace(/[&<>"']/g, function(m) {
                return escapeMap[m];
            });
        },
        
        /**
         * Save conversation to local storage
         */
        saveConversation: function() {
            if (!this.state.conversationId) {
                return;
            }
            
            const conversation = {
                id: this.state.conversationId,
                messages: this.state.messages,
                timestamp: new Date().getTime(),
                humanTakeover: this.state.humanTakeover
            };
            
            try {
                localStorage.setItem('openai_chatbot_conversation', JSON.stringify(conversation));
            } catch (e) {
                console.error('Failed to save conversation to local storage', e);
            }
        },
        
        /**
         * Load conversation from local storage
         */
        loadConversation: function() {
            try {
                const savedConversation = localStorage.getItem('openai_chatbot_conversation');
                
                if (!savedConversation) {
                    return;
                }
                
                const conversation = JSON.parse(savedConversation);
                
                // Check if conversation is not too old (10 hours)
                const now = new Date().getTime();
                const maxAge = 10 * 60 * 60 * 1000; // 10 hours
                
                if (now - conversation.timestamp > maxAge) {
                    localStorage.removeItem('openai_chatbot_conversation');
                    return;
                }
                
                // Restore conversation
                this.state.conversationId = conversation.id;
                this.state.humanTakeover = conversation.humanTakeover || false;
                
                // Clear welcome message
                this.elements.$chatMessages.empty();
                
                // Add messages to UI
                conversation.messages.forEach(message => {
                    if (message.role === 'user') {
                        this.addUserMessage(message.content);
                    } else if (message.role === 'assistant') {
                        this.addAssistantMessage(message.content, message.isHuman);
                    }
                });
            } catch (e) {
                console.error('Failed to load conversation from local storage', e);
                // Reset the local storage if it's corrupted
                localStorage.removeItem('openai_chatbot_conversation');
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        Chatbot.init();
    });
    
    // Expose API for external use
    window.OpenAIChatbot = {
        open: function() {
            Chatbot.openChat();
        },
        close: function() {
            Chatbot.closeChat();
        },
        toggle: function() {
            Chatbot.toggleChat();
        },
        sendMessage: function(message) {
            if (!Chatbot.state.isOpen) {
                Chatbot.openChat();
            }
            Chatbot.sendMessage(message);
        },
        clearChat: function() {
            Chatbot.clearChat();
        }
    };
    
})(jQuery);