<?php



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/constants.php';

require_once __DIR__ . '/../includes/auth.php';



requireRole(ROLE_CUSTOMER);



$db = Database::getConnection();



$customerId = currentUserId();



$message = '';

$error = '';



/*

|--------------------------------------------------------------------------

| CUSTOMER NAME

|--------------------------------------------------------------------------

*/



$customerName =

    $_SESSION['full_name']

    ?? 'Customer';



$firstName =

    explode(

        ' ',

        trim($customerName)

    )[0];





/*

|--------------------------------------------------------------------------

| SUBMIT FEEDBACK

|--------------------------------------------------------------------------

*/



if (

    $_SERVER['REQUEST_METHOD'] === 'POST' &&

    isset($_POST['submit_feedback'])

) {



    $orderId =

        filter_input(

            INPUT_POST,

            'order_id',

            FILTER_VALIDATE_INT

        );



    $rating =

        filter_input(

            INPUT_POST,

            'rating',

            FILTER_VALIDATE_INT

        );



    $comments =

        trim(

            $_POST['comments']

            ?? ''

        );





    if (

        !$orderId ||

        $orderId <= 0

    ) {



        $error =

            'Invalid order.';



    } elseif (

        !$rating ||

        $rating < 1 ||

        $rating > 5

    ) {



        $error =

            'Please select a rating from 1 to 5.';



    } else {



        try {



            /*

            |--------------------------------------------------------------------------

            | VERIFY ORDER

            |--------------------------------------------------------------------------

            |

            | The customer must:

            | - own the order

            | - have a Completed order

            | - have an assigned driver

            |

            */



            $orderStmt = $db->prepare("

                SELECT

                    o.order_id,

                    o.order_number,

                    o.customer_id,

                    o.driver_id,

                    o.completion_status,

                    d.full_name AS driver_name



                FROM `order` o



                LEFT JOIN driver d

                    ON d.driver_id = o.driver_id



                WHERE o.order_id = ?

                  AND o.customer_id = ?

                LIMIT 1

            ");



            $orderStmt->execute([

                $orderId,

                $customerId

            ]);



            $order =

                $orderStmt->fetch(PDO::FETCH_ASSOC);





            if (!$order) {



                throw new RuntimeException(

                    'Order not found.'

                );

            }





            if (

                $order['completion_status']

                !== 'Completed'

            ) {



                throw new RuntimeException(

                    'You can only give feedback for completed deliveries.'

                );

            }





            if (

                empty($order['driver_id'])

            ) {



                throw new RuntimeException(

                    'This order does not have an assigned driver.'

                );

            }





            /*

            |--------------------------------------------------------------------------

            | CHECK EXISTING FEEDBACK

            |--------------------------------------------------------------------------

            */



            $existingStmt = $db->prepare("

                SELECT

                    feedback_id



                FROM feedback



                WHERE order_id = ?

                LIMIT 1

            ");



            $existingStmt->execute([

                $orderId

            ]);



            $existingFeedback =

                $existingStmt->fetch(PDO::FETCH_ASSOC);





            if ($existingFeedback) {



                throw new RuntimeException(

                    'You have already submitted feedback for this delivery.'

                );

            }





            /*

            |--------------------------------------------------------------------------

            | INSERT FEEDBACK

            |--------------------------------------------------------------------------

            */



            $feedbackStmt = $db->prepare("

                INSERT INTO feedback

                (

                    order_id,

                    customer_id,

                    driver_id,

                    rating,

                    comments

                )

                VALUES

                (

                    ?,

                    ?,

                    ?,

                    ?,

                    ?

                )

            ");



            $feedbackStmt->execute([

                $orderId,

                $customerId,

                (int) $order['driver_id'],

                $rating,

                $comments !== ''

                    ? $comments

                    : null

            ]);





            $message =

                'Thank you! Your feedback has been submitted successfully.';





        } catch (Throwable $e) {



            $error =

                $e->getMessage();

        }

    }

}





/*

|--------------------------------------------------------------------------

| GET CUSTOMER'S COMPLETED ORDERS

|--------------------------------------------------------------------------

*/



$feedbackPage = max(1, (int)($_GET['page'] ?? 1));
$feedbackPerPage = 5;

/* Count all completed deliveries for this customer. */
$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM `order`
    WHERE customer_id = ?
      AND completion_status = 'Completed'
");
$countStmt->execute([$customerId]);
$totalCompletedOrders = (int)$countStmt->fetchColumn();

$totalFeedbackPages = max(
    1,
    (int)ceil($totalCompletedOrders / $feedbackPerPage)
);

if ($feedbackPage > $totalFeedbackPages) {
    $feedbackPage = $totalFeedbackPages;
}

$feedbackOffset = ($feedbackPage - 1) * $feedbackPerPage;


$ordersStmt = $db->prepare("

    SELECT



        o.order_id,

        o.order_number,

        o.order_date,

        o.expected_delivery_date,

        o.completion_status,

        o.driver_id,



        d.full_name AS driver_name,



        f.feedback_id,

        f.rating,

        f.comments,

        f.created_at AS feedback_created_at



    FROM `order` o



    LEFT JOIN driver d

        ON d.driver_id = o.driver_id



    LEFT JOIN feedback f

        ON f.order_id = o.order_id

       AND f.customer_id = ?



    WHERE o.customer_id = ?

      AND o.completion_status = 'Completed'



    ORDER BY

        o.order_date DESC,

        o.order_id DESC

    LIMIT $feedbackPerPage
    OFFSET $feedbackOffset

");



$ordersStmt->execute([

    $customerId,

    $customerId

]);



$completedOrders =

    $ordersStmt->fetchAll(PDO::FETCH_ASSOC);





/*

|--------------------------------------------------------------------------

| NAVIGATION

|--------------------------------------------------------------------------

*/



$navItems = [



    [

        'label' => 'Dashboard',

        'url' =>

            BASE_URL .

            'customer/dashboard.php'

    ],



    [

        'label' => 'Cart',

        'url' =>

            BASE_URL .

            'customer/cart.php'

    ],



    [

        'label' => 'My Orders',

        'url' =>

            BASE_URL .

            'customer/order_history.php'

    ],



    [

        'label' => 'Track Delivery',

        'url' =>

            BASE_URL .

            'customer/track_delivery.php'

    ],



    [

        'label' => 'Feedback',

        'url' =>

            BASE_URL .

            'customer/feedback.php'

    ],



    [

        'label' => 'Notifications',

        'url' =>

            BASE_URL .

            'customer/notifications.php'

    ],



    [

        'label' => 'Profile',

        'url' =>

            BASE_URL .

            'customer/profile.php'

    ]



];



?>



<!DOCTYPE html>



<html lang="en">



<head>



<meta charset="UTF-8">



<meta

    name="viewport"

    content="width=device-width, initial-scale=1.0"

>



<title>

    Feedback · Hong Sing LKRR Commercial

</title>



<link

    rel="stylesheet"

    href="<?= BASE_URL ?>assets/css/theme.css"

>



<style>
/* ==========================================================
   FEEDBACK PAGE
========================================================== */
.feedback-page {
    width: 100%;
    max-width: 1180px;
    margin: 0 auto;
}

.page-header {
    margin-bottom: 24px;
}

.page-header h1 {
    margin: 0 0 7px;
    font-size: 30px;
    font-weight: 800;
    color: var(--color-text-primary);
}

.page-header p {
    margin: 0;
    max-width: 720px;
    color: var(--color-text-secondary);
    line-height: 1.6;
}

/* ALERTS */
.feedback-alert {
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 14px;
    line-height: 1.5;
}

.feedback-alert.success {
    background: rgba(34,197,94,.09);
    border: 1px solid rgba(34,197,94,.24);
    color: #15803d;
}

.feedback-alert.error {
    background: rgba(239,68,68,.08);
    border: 1px solid rgba(239,68,68,.24);
    color: #b91c1c;
}

/* LIST */
.feedback-list {
    display: grid;
    gap: 18px;
}

/* CARD */
.feedback-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    overflow: hidden;
}

.feedback-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 20px 22px;
    border-bottom: 1px solid var(--color-border);
    background: var(--color-surface-alt);
}

.order-number {
    margin-bottom: 7px;
    font-size: 17px;
    font-weight: 800;
    color: var(--color-text-primary);
}

.delivery-info {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 18px;
    font-size: 13px;
    line-height: 1.6;
    color: var(--color-text-secondary);
}

.driver-name {
    font-weight: 700;
    color: var(--color-text-primary);
}

.feedback-status {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.feedback-status.completed {
    background: rgba(34,197,94,.10);
    color: #15803d;
}

.feedback-status.submitted {
    background: rgba(59,130,246,.10);
    color: #2563eb;
}

/* FORM / EXISTING FEEDBACK BODY */
.feedback-card form,
.existing-feedback {
    margin: 0;
    padding: 22px;
}

.rating-section {
    margin-bottom: 20px;
}

.rating-label,
.comments-label {
    display: block;
    margin-bottom: 9px;
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text-primary);
}

/* STAR RATING */
.rating-stars {
    display: inline-flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
    padding: 4px 0;
}

.rating-stars input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.rating-stars label {
    margin: 0;
    font-size: 34px;
    line-height: 1;
    color: #d1d5db;
    cursor: pointer;
    transition: transform .15s ease, color .15s ease;
}

.rating-stars label:hover {
    transform: translateY(-1px);
}

.rating-stars label:hover,
.rating-stars label:hover ~ label,
.rating-stars input:checked ~ label {
    color: #f59e0b;
}

/* COMMENTS */
.comments-input {
    display: block;
    width: 100%;
    min-height: 110px;
    box-sizing: border-box;
    resize: vertical;
    padding: 12px 13px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    color: var(--color-text-primary);
    font: inherit;
    line-height: 1.5;
    outline: none;
}

.comments-input:focus {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px var(--color-accent-soft);
}

.submit-feedback {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 155px;
    margin-top: 16px;
    padding: 11px 18px;
    border: 0;
    border-radius: var(--radius-md);
    background: var(--color-accent);
    color: #fff;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
    transition: .15s ease;
}

.submit-feedback:hover {
    background: var(--color-accent-hover);
}

/* SUBMITTED FEEDBACK */
.existing-feedback {
    background: var(--color-surface);
    border: 0;
    border-radius: 0;
}

.existing-feedback > strong {
    display: block;
    margin-bottom: 10px;
    font-size: 13px;
    color: var(--color-text-secondary);
}

.existing-rating {
    display: flex;
    gap: 3px;
    margin-bottom: 14px;
}

.existing-star {
    font-size: 24px;
    line-height: 1;
    color: #f59e0b;
}

.existing-star.empty {
    color: #d1d5db;
}

.existing-comment {
    padding: 13px 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface-alt);
    color: var(--color-text-primary);
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.feedback-date {
    margin-top: 10px;
    font-size: 11px;
    color: var(--color-text-muted);
}

/* EMPTY STATE */
.empty-feedback {
    padding: 55px 24px;
    text-align: center;
    background: var(--color-surface);
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-lg);
}

.empty-feedback-icon {
    margin-bottom: 12px;
    font-size: 38px;
}

.empty-feedback h2 {
    margin: 0 0 8px;
    font-size: 20px;
    color: var(--color-text-primary);
}

.empty-feedback p {
    max-width: 520px;
    margin: 0 auto;
    color: var(--color-text-secondary);
    line-height: 1.6;
}


/* PAGINATION */
.feedback-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 22px;
    padding: 14px 16px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
}

.feedback-pagination-info {
    font-size: 12px;
    color: var(--color-text-secondary);
}

.feedback-pagination-links {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.feedback-pagination-links a,
.feedback-pagination-links span {
    display: inline-flex;
    min-width: 36px;
    height: 36px;
    align-items: center;
    justify-content: center;
    padding: 0 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    color: var(--color-text-primary);
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

.feedback-pagination-links a:hover {
    border-color: var(--color-accent);
    color: var(--color-accent);
}

.feedback-pagination-links .active {
    border-color: var(--color-accent);
    background: var(--color-accent);
    color: #fff;
}

.feedback-pagination-links .disabled {
    opacity: .45;
    cursor: not-allowed;
}

/* RESPONSIVE */
@media (max-width: 700px) {
    .feedback-page {
        max-width: 100%;
    }

    .page-header h1 {
        font-size: 25px;
    }

    .feedback-card-header {
        flex-direction: column;
        padding: 17px;
    }

    .feedback-card form,
    .existing-feedback {
        padding: 17px;
    }

    .feedback-status {
        align-self: flex-start;
    }

    .rating-stars label {
        font-size: 31px;
    }

    .submit-feedback {
        width: 100%;
    }

    .feedback-pagination {
        flex-direction: column;
        align-items: stretch;
    }

    .feedback-pagination-info {
        text-align: center;
    }

    .feedback-pagination-links {
        justify-content: center;
    }
}
</style>



</head>





<body>



<div class="app-layout">



<?php



require __DIR__ .

    '/../includes/header.php';



?>





<main class="app-main">

<div class="feedback-page">





<!-- ==========================================================

     PAGE HEADER

\========================================================== -->



<div class="page-header">



    <h1>

        Delivery Feedback

    </h1>



    <p>

        Rate your delivery experience and help us improve our driver's performance.

    </p>



</div>





<!-- ==========================================================

     ALERTS

\========================================================== -->



<?php if ($message): ?>



    <div class="feedback-alert success">



        <?= htmlspecialchars($message) ?>



    </div>



<?php endif; ?>





<?php if ($error): ?>



    <div class="feedback-alert error">



        <?= htmlspecialchars($error) ?>



    </div>



<?php endif; ?>





<!-- ==========================================================

     FEEDBACK LIST

\========================================================== -->



<div class="feedback-list">





<?php if (empty($completedOrders)): ?>





    <div class="empty-feedback">



        <div class="empty-feedback-icon">

            ⭐

        </div>



        <h2>

            No completed deliveries yet

        </h2>



        <p>

            Once one of your deliveries is completed,

            you will be able to rate the driver's performance here.

        </p>



    </div>





<?php else: ?>





    <?php foreach ($completedOrders as $order): ?>





        <div class="feedback-card">





            <!-- HEADER -->



            <div class="feedback-card-header">



                <div>



                    <div class="order-number">



                        Order #

                        <?= htmlspecialchars(

                            $order['order_number']

                        ) ?>



                    </div>





                    <div class="delivery-info">



                        Driver:



                        <?php if (

                            !empty(

                                $order['driver_name']

                            )

                        ): ?>



                            <span class="driver-name">



                                <?= htmlspecialchars(

                                    $order['driver_name']

                                ) ?>



                            </span>



                        <?php else: ?>



                            <span>

                                Driver information unavailable

                            </span>



                        <?php endif; ?>





                        <br>





                        Delivered:



                        <?= !empty(

                            $order['expected_delivery_date']

                        )

                            ? date(

                                'M d, Y',

                                strtotime(

                                    $order[

                                        'expected_delivery_date'

                                    ]

                                )

                            )

                            : 'Completed'

                        ?>



                    </div>



                </div>





                <?php if (

                    !empty(

                        $order['feedback_id']

                    )

                ): ?>



                    <span

                        class="feedback-status submitted"

                    >

                        Feedback Submitted

                    </span>



                <?php else: ?>



                    <span

                        class="feedback-status completed"

                    >

                        Completed

                    </span>



                <?php endif; ?>



            </div>





            <!-- EXISTING FEEDBACK -->



            <?php if (

                !empty(

                    $order['feedback_id']

                )

            ): ?>





                <div class="existing-feedback">



                    <strong>

                        Your Feedback

                    </strong>





                    <div class="existing-rating">



                        <?php for (

                            $i = 1;

                            $i <= 5;

                            $i++

                        ): ?>



                            <span

                                class="existing-star <?= $i <= (int)$order['rating']

                                    ? ''

                                    : 'empty' ?>"

                            >

                                ★

                            </span>



                        <?php endfor; ?>



                    </div>





                    <?php if (

                        !empty(

                            $order['comments']

                        )

                    ): ?>



                        <div class="existing-comment">



                            <?= nl2br(

                                htmlspecialchars(

                                    $order['comments']

                                )

                            ) ?>



                        </div>



                    <?php else: ?>



                        <div class="existing-comment">



                            No comment provided.



                        </div>



                    <?php endif; ?>





                    <?php if (

                        !empty(

                            $order['feedback_created_at']

                        )

                    ): ?>



                        <div class="feedback-date">



                            Submitted on

                            <?= date(

                                'M d, Y h:i A',

                                strtotime(

                                    $order[

                                        'feedback_created_at'

                                    ]

                                )

                            ) ?>



                        </div>



                    <?php endif; ?>



                </div>





            <?php else: ?>





                <!-- FEEDBACK FORM -->



                <?php if (

                    !empty(

                        $order['driver_id']

                    )

                ): ?>





                    <form

                        method="POST"

                        action=""

                    >



                        <input

                            type="hidden"

                            name="order_id"

                            value="<?= (int)$order['order_id'] ?>"

                        >





                        <!-- RATING -->



                        <div class="rating-section">



                            <label

                                class="rating-label"

                            >

                                How was your delivery experience?

                            </label>





                            <div class="rating-stars">



                                <input

                                    type="radio"

                                    id="star5-<?= (int)$order['order_id'] ?>"

                                    name="rating"

                                    value="5"

                                >



                                <label

                                    for="star5-<?= (int)$order['order_id'] ?>"

                                    title="5 - Excellent"

                                >

                                    ★

                                </label>





                                <input

                                    type="radio"

                                    id="star4-<?= (int)$order['order_id'] ?>"

                                    name="rating"

                                    value="4"

                                >



                                <label

                                    for="star4-<?= (int)$order['order_id'] ?>"

                                    title="4 - Very Good"

                                >

                                    ★

                                </label>





                                <input

                                    type="radio"

                                    id="star3-<?= (int)$order['order_id'] ?>"

                                    name="rating"

                                    value="3"

                                >



                                <label

                                    for="star3-<?= (int)$order['order_id'] ?>"

                                    title="3 - Good"

                                >

                                    ★

                                </label>





                                <input

                                    type="radio"

                                    id="star2-<?= (int)$order['order_id'] ?>"

                                    name="rating"

                                    value="2"

                                >



                                <label

                                    for="star2-<?= (int)$order['order_id'] ?>"

                                    title="2 - Fair"

                                >

                                    ★

                                </label>





                                <input

                                    type="radio"

                                    id="star1-<?= (int)$order['order_id'] ?>"

                                    name="rating"

                                    value="1"

                                >



                                <label

                                    for="star1-<?= (int)$order['order_id'] ?>"

                                    title="1 - Poor"

                                >

                                    ★

                                </label>



                            </div>



                        </div>





                        <!-- COMMENTS -->



                        <label

                            class="comments-label"

                            for="comments-<?= (int)$order['order_id'] ?>"

                        >

                            Comment

                            <span

                                style="font-weight:normal;color:var(--color-text-secondary);"

                            >

                                (optional)

                            </span>

                        </label>





                        <textarea

                            id="comments-<?= (int)$order['order_id'] ?>"

                            name="comments"

                            class="comments-input"

                            maxlength="1000"

                            placeholder="Tell us about your delivery experience..."

                        ></textarea>





                        <button

                            type="submit"

                            name="submit_feedback"

                            class="submit-feedback"

                        >

                            Submit Feedback

                        </button>



                    </form>





                <?php else: ?>





                    <div class="existing-feedback">



                        Driver information is not available

                        for this completed order.



                    </div>





                <?php endif; ?>





            <?php endif; ?>





        </div>





    <?php endforeach; ?>





<?php endif; ?>





</div>

<?php if ($totalCompletedOrders > $feedbackPerPage): ?>

    <div class="feedback-pagination">

        <div class="feedback-pagination-info">
            Showing
            <?= $totalCompletedOrders === 0 ? 0 : $feedbackOffset + 1 ?>
            to
            <?= min($feedbackOffset + $feedbackPerPage, $totalCompletedOrders) ?>
            of
            <?= $totalCompletedOrders ?>
            completed deliveries
        </div>

        <div class="feedback-pagination-links">

            <?php if ($feedbackPage > 1): ?>
                <a href="?page=<?= $feedbackPage - 1 ?>" aria-label="Previous page">
                    ‹
                </a>
            <?php else: ?>
                <span class="disabled">‹</span>
            <?php endif; ?>

            <?php
            $startPage = max(1, $feedbackPage - 2);
            $endPage = min($totalFeedbackPages, $feedbackPage + 2);

            if ($startPage > 1):
            ?>
                <a href="?page=1">1</a>

                <?php if ($startPage > 2): ?>
                    <span class="disabled">…</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a
                    href="?page=<?= $i ?>"
                    class="<?= $i === $feedbackPage ? 'active' : '' ?>"
                >
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($endPage < $totalFeedbackPages): ?>

                <?php if ($endPage < $totalFeedbackPages - 1): ?>
                    <span class="disabled">…</span>
                <?php endif; ?>

                <a href="?page=<?= $totalFeedbackPages ?>">
                    <?= $totalFeedbackPages ?>
                </a>

            <?php endif; ?>

            <?php if ($feedbackPage < $totalFeedbackPages): ?>
                <a href="?page=<?= $feedbackPage + 1 ?>" aria-label="Next page">
                    ›
                </a>
            <?php else: ?>
                <span class="disabled">›</span>
            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>

</div>

</main>



</div>



</body>



</html>