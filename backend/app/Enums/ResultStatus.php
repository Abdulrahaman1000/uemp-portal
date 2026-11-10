<?php

namespace App\Enums;

/**
 * Lifecycle of a course result submission:
 * draft -> submitted -> approved | rejected -> (lecturer corrects) -> submitted ...
 * An approved result can be reopened by an authorised admin (reopened), which lets the lecturer edit again.
 */
enum ResultStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reopened = 'reopened';

    /** A lecturer may change scores only in these states (business rule: submitted results are locked). */
    public function isEditableByLecturer(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Reopened], true);
    }

    /** Only these states can be submitted for review. */
    public function canBeSubmitted(): bool
    {
        return $this->isEditableByLecturer();
    }

    /** Only submitted results can be approved or rejected. */
    public function isAwaitingReview(): bool
    {
        return $this === self::Submitted;
    }

    /** Students only ever see approved results. */
    public function isVisibleToStudents(): bool
    {
        return $this === self::Approved;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
