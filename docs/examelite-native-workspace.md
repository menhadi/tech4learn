# Retired external ExamElite workspace

The external ExamElite workspace and its deployment script are retired.
The replacement uses the complete copied Laravel foundation in `platform/`,
retaining the attendance module and the existing platform administrator identity.
Previous organisations, learners, attendance records and linked media are not
carried forward. The original ExamElite installation must remain unchanged.

Use [native production setup](native-production-setup.md) for the replacement
acceptance and cutover requirements. The old script that installed changes into
the original ExamElite site has been removed. Live changes remain user-run under
the project's read-only assistant access rule.
