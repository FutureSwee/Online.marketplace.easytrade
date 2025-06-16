<?php
ob_start();
// chat.php - Chat interface for messaging sellers
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['user_id']) && $_GET['user_id'] == $_SESSION['user_id']) {
    // Store message in session instead of sending raw JSON
    $_SESSION['buy_message_error'] = json_encode([
        'message' => 'You cant buy what you are selling'
    ]);

    // Redirect
    header('Location: Categories.php');
    exit;
}
// Get current user ID
$current_user_id = $_SESSION['user_id'];

// Include header
include_once '../includes/header.php';
?>

<link rel="stylesheet" href="../public/css/chat.css">

<div class="container mt-4 mb-4">
    <h1>Messages</h1>

    <div class="chat-container">
        <!-- Chat Sidebar - List of conversations -->

        <!-- Chat Box - Actual conversation -->
        <div class="chat-box" id="chat-box">
            <div class="chat-header" id="chat-header">
                <div class="back-button" onclick="backToChatList()">←</div>
                <div class="chat-header-content">
                    <div class="chat-header-title">Select a conversation</div>
                </div>
             
            </div>

            <div class="message-container" id="message-container">
                <div class="text-center p-5">
                    <p>Select a conversation from the left to start messaging</p>
                </div>
            </div>

            <form class="message-form" id="message-form">
                <textarea class="message-input" id="message-input" placeholder="Type your message..."
                    rows="1"></textarea>
                <button type="submit" class="send-button">Send</button>
                <button class="rate-button" id="rate-button" onclick="openRatingModal()" ><i class="fas fa-star"></i>
                    Rate
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Rating Modal -->

<div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="ratingForm" action="../controllers/submit_rating.php" method="post" class="modal-content">
        <input type="hidden" name="listing_id" id="listing_id" value="" />
        <input type="hidden" name="seller_id" id="seller_id" value="" />
      <div class="modal-header">
        <h5 class="modal-title" id="ratingModalLabel">Rate this conversation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <p>How would you rate your experience with <span id="rating-user-name"></span>?</p>

        <div class="rating-stars mb-3" id="rating-stars" role="radiogroup" aria-label="Star rating">
          <label>
            <input type="radio" name="rating_score" value="1" class="d-none" />
            <span class="star" data-rating="1" role="radio" aria-checked="false" tabindex="0">★</span>
          </label>
          <label>
            <input type="radio" name="rating_score" value="2" class="d-none" />
            <span class="star" data-rating="2" role="radio" aria-checked="false" tabindex="-1">★</span>
          </label>
          <label>
            <input type="radio" name="rating_score" value="3" class="d-none" />
            <span class="star" data-rating="3" role="radio" aria-checked="false" tabindex="-1">★</span>
          </label>
          <label>
            <input type="radio" name="rating_score" value="4" class="d-none" />
            <span class="star" data-rating="4" role="radio" aria-checked="false" tabindex="-1">★</span>
          </label>
          <label>
            <input type="radio" name="rating_score" value="5" class="d-none" />
            <span class="star" data-rating="5" role="radio" aria-checked="false" tabindex="-1">★</span>
          </label>
        </div>

        <div class="mb-3 text-start">
          <label for="rating-comment" class="form-label">Comment (optional)</label>
          <textarea class="form-control" id="rating-comment" name="review_text" rows="3" placeholder="Share your experience..."></textarea>
        </div>

        <div id="rating-success" class="alert alert-success d-none">
          Rating submitted successfully!
        </div>

        <div id="rating-error" class="alert alert-danger d-none">
          Failed to submit rating. Please try again.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submit-rating" disabled>Submit Rating</button>
      </div>
    </form>
  </div>
</div>


<style>
    /* Additional CSS for rating functionality */
    .chat-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .rate-button {
        background: #28a745;
        border: none;
        color: white;
        padding: 8px 12px;
        border-radius: 20px;
        font-size: 14px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .rate-button:hover {
        background: #218838;
    }

    .rating-stars {
        font-size: 2rem;
        color: #ddd;
        cursor: pointer;
    }

    .rating-stars .star {
        transition: color 0.2s ease;
        margin: 0 2px;
    }

    .rating-stars .star:hover,
    .rating-stars .star.active {
        color: #ffc107;
    }

    .rating-stars .star.active~.star {
        color: #ddd;
    }

    @media (max-width: 768px) {
        .chat-header {
            flex-wrap: wrap;
        }

        .chat-actions {
            width: 100%;
            justify-content: flex-end;
            margin-top: 5px;
        }
    }
</style>



<script src="../public/js/chat.js?v=0.1"></script>

<?php
// Include footer
include_once '../includes/footer.php';
?>