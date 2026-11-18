<?php
$showFeeDetails = course_should_show_fee_details($course);
$isFreeProgram = !course_requires_payment($course);
$isLiveProgram = ($course['delivery_type'] ?? 'video') === 'live_session';
$regularFee = max(0, (float) ($course['fee'] ?? 0));
$hasCourseDiscount = $showFeeDetails && $regularFee > 0 && course_fee_amount($course) < $regularFee;
?>
<article class="course-card academy-program" data-program-card data-format="<?= $isLiveProgram ? 'live' : 'video' ?>" data-search="<?= e(strtolower($course['title'] . ' ' . $course['short_description'])) ?>">
    <div class="program-art <?= $isLiveProgram ? 'program-art-live' : '' ?>" aria-hidden="true"><span class="program-art-label"><?= $isLiveProgram ? 'LIVE LEARNING' : 'DATA &middot; INSIGHT &middot; IMPACT' ?></span><div class="program-art-graphic"><i></i><i></i><i></i><i></i><i></i></div><span class="program-art-mark">elldy / academy</span></div>
    <div class="program-card-content"><div class="course-topline"><span><?= $isLiveProgram ? 'Live sessions' : 'Video learning' ?></span><span><?= e($course['duration']) ?></span></div>
    <h3><a href="<?= e(program_url($course)) ?>"><?= e($course['title']) ?></a></h3><p><?= e($course['short_description']) ?></p>
    <?php if ($hasCourseDiscount): ?><span class="course-badge offer">Special program price</span><?php endif; ?>
    <div class="program-card-footer"><div><?php if ($showFeeDetails): ?><?= public_price_html($course, 'program') ?><?php else: ?><span class="program-access">Enrollment available</span><?php endif; ?></div><a href="<?= e(program_url($course)) ?>" aria-label="<?= e('View program: ' . $course['title']) ?>">View program <span aria-hidden="true">&rarr;</span></a></div></div>
</article>
