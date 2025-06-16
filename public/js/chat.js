// chat.js - Chat functionality JavaScript

// Global variables
let currentListingId = null;
let currentReceiverId = null;
let messageInterval = null;

// Global DOM references (set later)
let messageContainer, messageInput, chatHeader;

// Load chat messages
function loadChatMessages() {
    if (!currentListingId || !currentReceiverId) return;

    fetch(`../controllers/get_chat_messages.php?listing_id=${currentListingId}&user_id=${currentReceiverId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                messageContainer.innerHTML = `<div class="error-message">${data.error}</div>`;
                return;
            }

            if (data.listing) {
                chatHeader.innerHTML = `
                    <div class="back-button" onclick="backToChatList()">←</div>
                    <div class="chat-header-content">
                        <div class="chat-header-title">${data.listing.item_name}</div>
                        <div class="chat-header-price">R${data.listing.price}</div>
                     
                    </div>
                `;
            }

            if (data.messages.length === 0) {
                messageContainer.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
                return;
            }

            let html = '';
            let previousDate = '';

            data.messages.forEach(message => {
                const messageDate = new Date(message.created_at);
                const formattedDate = messageDate.toLocaleDateString();

                if (formattedDate !== previousDate) {
                    html += `<div class="date-separator">${formattedDate}</div>`;
                    previousDate = formattedDate;
                }
                // console.log("message.sender_id:" + message.sender_id);
                //  console.log("currentReceiverId:" + currentReceiverId);
                

                console.log("START OF MESSAGE CLASS");
                const formattedTime = messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const messageClass = Number(message.sender_id) === Number(currentReceiverId) ? 'received' : 'sent';
                // console.log("Type of message.sender_id:", typeof message.sender_id);
                // console.log("Type of currentReceiverId:", typeof currentReceiverId);

                const answered = message.sender_id === currentReceiverId;
                // console.log("answered:" + answered);
                // console.log("messageClass:" + messageClass);
                // console.log("END OF MESSAGE CLASS");
                html += `
                    <div class="message ${messageClass}">
                        <div class="message-content">${message.message}</div>
                        <div class="message-time">${formattedTime}</div>
                    </div>
                `;
            });

            messageContainer.innerHTML = html;
            messageContainer.scrollTop = messageContainer.scrollHeight;
        })
        .catch(error => {
            console.error('Error loading messages:', error);
            messageContainer.innerHTML = '<div class="error-message">Failed to load messages</div>';
        });
}


// Open chat globally
function openChat(listingId, userId) {
    currentListingId = listingId;
    currentReceiverId = userId;

    const url = new URL(window.location);
    url.searchParams.set('listing_id', listingId);
    url.searchParams.set('user_id', userId);
    window.history.pushState({}, '', url);

    document.querySelector('.chat-container').classList.add('chat-open');
    if (window.innerWidth <= 768) {
        document.querySelector('.chat-list').style.display = 'none';
    }

    if (messageInterval) clearInterval(messageInterval);
    loadChatMessages();
    messageInterval = setInterval(loadChatMessages, 5000);
}

// Back to chat list globally
function backToChatList() {
    document.querySelector('.chat-container').classList.remove('chat-open');
    document.querySelector('.chat-list').style.display = 'block';

    const url = new URL(window.location);
    url.searchParams.delete('listing_id');
    url.searchParams.delete('user_id');
    window.history.pushState({}, '', url);

    if (messageInterval) {
        clearInterval(messageInterval);
        messageInterval = null;
    }
}

// DOMContentLoaded main init
document.addEventListener('DOMContentLoaded', function () {
    const chatList = document.getElementById('chat-list');
    const messageForm = document.getElementById('message-form');
    chatHeader = document.getElementById('chat-header');
    messageInput = document.getElementById('message-input');
    messageContainer = document.getElementById('message-container');

    // Read params and open chat or show list
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('listing_id') && urlParams.has('user_id')) {
        const listingId = urlParams.get('listing_id');
        const userId = urlParams.get('user_id');
        openChat(listingId, userId);
    } else {
        loadChatList();
    }

    // Submit message
    if (messageForm) {
        messageForm.addEventListener('submit', function (e) {
            e.preventDefault();
            sendMessage();
        });
    }

    // Load user chat list
    function loadChatList() {
        fetch('../controllers/get_user_chats.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    chatList.innerHTML = `<div class="error-message">${data.error}</div>`;
                    return;
                }

                if (data.chats.length === 0) {
                    chatList.innerHTML = '<div class="no-chats">No conversations yet</div>';
                    return;
                }

                let html = '';
                data.chats.forEach(chat => {
                    const unreadBadge = chat.unread_count > 0 ?
                        `<span class="unread-badge">${chat.unread_count}</span>` : '';

                    html += `
                        <div class="chat-item" onclick="openChat(${chat.listing_id}, ${chat.other_user_id})">
                            <div class="chat-item-title">
                                <strong>${chat.item_name}</strong>
                                <span class="chat-price">R${chat.price}</span>
                            </div>
                            <div class="chat-item-user">
                                <span>with ${chat.other_user_name}</span>
                                ${unreadBadge}
                            </div>
                            <div class="chat-item-preview">${chat.last_message || 'No messages yet'}</div>
                        </div>
                    `;
                });

                chatList.innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading chats:', error);
                chatList.innerHTML = '<div class="error-message">Failed to load chats</div>';
            });
        
        
        document.getElementById('ratingModal').addEventListener('show.bs.modal', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const listingId = urlParams.get('listing_id');
    const userId = urlParams.get('user_id');
  
    document.getElementById('listing_id').value = listingId || '';
    document.getElementById('seller_id').value = userId || '';
  });
    }

    // Send a message
    function sendMessage() {
        if (!currentListingId || !currentReceiverId) return;

        const message = messageInput.value.trim();
        if (!message) return;

        messageInput.value = '';

        const data = {
            listing_id: currentListingId,
            receiver_id: currentReceiverId,
            message: message
        };

        fetch('../controllers/send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error sending message:', data.error);
                    return;
                }

                loadChatMessages();
            })
            .catch(error => {
                console.error('Error sending message:', error);
            });
    }
});



let selectedRating = 0;
let currentChatUserId = null;
let currentChatUserName = '';

// Function to show/hide rating button when a conversation is selected
function showRatingButton(userId, userName) {
    currentChatUserId = userId;
    currentChatUserName = userName;
    document.getElementById('rate-button').style.display = 'block';
}

// Function to hide rating button
function hideRatingButton() {
    document.getElementById('rate-button').style.display = 'none';
    currentChatUserId = null;
    currentChatUserName = '';
}

// Function to open rating modal
function openRatingModal() {
    console.log('Opening rating modal for user:', currentChatUserId, currentChatUserName);

    document.getElementById('rating-user-name').textContent = currentChatUserName;

    // Reset modal state
    selectedRating = 0;
    document.getElementById('rating-comment').value = '';
    document.querySelectorAll('.star').forEach(star => star.classList.remove('active'));
    document.getElementById('submit-rating').disabled = true;
    document.getElementById('rating-success').classList.add('d-none');
    document.getElementById('rating-error').classList.add('d-none');

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('ratingModal'));
    modal.show();
}

// Star rating functionality
document.addEventListener('DOMContentLoaded', function () {
    const stars = document.querySelectorAll('.star');

    stars.forEach(star => {
        star.addEventListener('click', function () {
            selectedRating = parseInt(this.dataset.rating);
            updateStarDisplay();
            document.getElementById('submit-rating').disabled = false;
        });

        star.addEventListener('mouseenter', function () {
            const rating = parseInt(this.dataset.rating);
            highlightStars(rating);
        });
    });

    document.getElementById('rating-stars').addEventListener('mouseleave', function () {
        updateStarDisplay();
    });

    // Submit rating
    document.getElementById('submit-rating').addEventListener('click', function () {
        submitRating();
    });
});

function highlightStars(rating) {
    const stars = document.querySelectorAll('.star');
    stars.forEach((star, index) => {
        if (index < rating) {
            star.style.color = '#ffc107';
        } else {
            star.style.color = '#ddd';
        }
    });
}

function updateStarDisplay() {
    const stars = document.querySelectorAll('.star');
    stars.forEach((star, index) => {
        star.classList.remove('active');
        if (index < selectedRating) {
            star.classList.add('active');
        }
    });
}

function submitRating() {
    if (!selectedRating) return;

    const comment = document.getElementById('rating-comment').value.trim();

    // Get URL params for listing and seller
    const urlParams = new URLSearchParams(window.location.search);
    const listing_id = urlParams.get('listing_id');
    const seller_id = urlParams.get('user_id'); // assuming user_id = seller_id

    if (!listing_id || !seller_id) {
        console.error('Missing listing_id or seller_id in URL');
        return;
    }

    // Disable submit button and show submitting text
    const submitBtn = document.getElementById('submit-rating');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Submitting...';
    submitBtn.disabled = true;

    // Create a form dynamically
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../controllers/submit_rating.php';

    // Add hidden inputs
    const inputs = {
        listing_id: listing_id,
        seller_id: seller_id,
        rating_score: selectedRating,
        review_text: comment
    };

    for (const [name, value] of Object.entries(inputs)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();

    // Note: no need to re-enable button here, page will reload or redirect
}


document.getElementById('ratingModal').addEventListener('show.bs.modal', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const listingId = urlParams.get('listing_id');
    const userId = urlParams.get('user_id');
  
    document.getElementById('listing_id').value = listingId || '';
    document.getElementById('seller_id').value = userId || '';
  });
  