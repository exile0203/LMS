import { router, usePage } from '@inertiajs/vue3';
import { Inbox, Star, Clock, Send, File, Trash2, Tag, Archive } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { AppPageProps } from '@/types';
import { MAIL_FOLDER, type MailEmail, type MailFolder, type MailFolderName, type MailPageProps } from './types';

export type { MailPageProps } from './types';

export function useMailPage(props: MailPageProps) {
    const page = usePage<AppPageProps>();

    const allEmails = ref<MailEmail[]>(props.emails);
    const pagination = ref(props.pagination);
    const isLoadingMoreEmails = ref(false);
    const isAppendingEmails = ref(false);
    const isComposing = ref(false);
    const composeForm = ref({
        to: '',
        subject: '',
        body: '',
    });

    const flashSuccess = computed(() => {
        const flash = page.props.flash as { success?: string; error?: string };
        return flash?.success ?? '';
    });

    const flashError = computed(() => {
        const flash = page.props.flash as { success?: string; error?: string };
        return flash?.error ?? '';
    });

    watch(
        () => props.emails,
        (nextEmails) => {
            if (isAppendingEmails.value) {
                const existingIds = new Set(allEmails.value.map((email) => email.id));
                const uniqueNextEmails = nextEmails.filter((email) => !existingIds.has(email.id));
                allEmails.value = [...allEmails.value, ...uniqueNextEmails];
                return;
            }

            allEmails.value = nextEmails;
        },
    );

    watch(
        () => props.pagination,
        (nextPagination) => {
            pagination.value = nextPagination;
        },
    );

    const currentFolder = ref<MailFolderName>(MAIL_FOLDER.INBOX);

    const folderBase: Array<Pick<MailFolder, 'name' | 'icon'>> = [
        { name: MAIL_FOLDER.INBOX, icon: Inbox },
        { name: MAIL_FOLDER.STARRED, icon: Star },
        { name: MAIL_FOLDER.SNOOZED, icon: Clock },
        { name: MAIL_FOLDER.SENT, icon: Send },
        { name: MAIL_FOLDER.DRAFTS, icon: File },
        { name: MAIL_FOLDER.SPAM, icon: Tag },
        { name: MAIL_FOLDER.ARCHIVED, icon: Archive },
        { name: MAIL_FOLDER.TRASH, icon: Trash2 },
    ];

    const currentEmails = computed(() => {
        if (currentFolder.value === MAIL_FOLDER.STARRED) {
            return allEmails.value.filter((email) => email.starred && email.folder !== MAIL_FOLDER.TRASH);
        }
        return allEmails.value.filter((email) => email.folder === currentFolder.value);
    });

    const activeFolders = computed(() =>
        folderBase.map((folder) => ({
            ...folder,
            active: folder.name === currentFolder.value,
            count:
                folder.name === MAIL_FOLDER.STARRED
                    ? allEmails.value.filter((email) => email.starred && email.folder !== MAIL_FOLDER.TRASH).length
                    : allEmails.value.filter((email) => email.folder === folder.name).length,
        })),
    );

    const selectFolder = (folderName: MailFolderName) => {
        currentFolder.value = folderName;
    };

    const patchEmailWithRollback = (
        emailId: number,
        applyOptimisticUpdate: (email: MailEmail) => void,
        patchRequest: (onError: () => void) => void,
    ) => {
        const email = allEmails.value.find((item) => item.id === emailId);

        if (!email) {
            patchRequest(() => {});
            return;
        }

        const snapshot: MailEmail = { ...email };
        applyOptimisticUpdate(email);

        patchRequest(() => {
            Object.assign(email, snapshot);
        });
    };

    const toggleStar = (emailId: number) => {
        patchEmailWithRollback(
            emailId,
            (email) => {
                email.starred = !email.starred;
            },
            (onError) => {
                router.patch(`/mail/${emailId}/star`, {}, { preserveScroll: true, onError });
            },
        );
    };

    const deleteEmail = (emailId: number) => {
        patchEmailWithRollback(
            emailId,
            (email) => {
                email.folder = MAIL_FOLDER.TRASH;
            },
            (onError) =>
                router.patch(
                    `/mail/${emailId}/folder`,
                    { folder: MAIL_FOLDER.TRASH },
                    { preserveScroll: true, onError },
                ),
        );
    };

    const archiveEmail = (emailId: number) => {
        patchEmailWithRollback(
            emailId,
            (email) => {
                email.folder = MAIL_FOLDER.ARCHIVED;
            },
            (onError) =>
                router.patch(
                    `/mail/${emailId}/folder`,
                    { folder: MAIL_FOLDER.ARCHIVED },
                    { preserveScroll: true, onError },
                ),
        );
    };

    const markAsRead = (emailId: number) => {
        patchEmailWithRollback(
            emailId,
            (email) => {
                email.unread = false;
            },
            (onError) => {
                router.patch(`/mail/${emailId}/read`, {}, { preserveScroll: true, onError });
            },
        );
    };

    const snoozeEmail = (emailId: number) => {
        patchEmailWithRollback(
            emailId,
            (email) => {
                email.folder = MAIL_FOLDER.SNOOZED;
            },
            (onError) =>
                router.patch(
                    `/mail/${emailId}/folder`,
                    { folder: MAIL_FOLDER.SNOOZED },
                    { preserveScroll: true, onError },
                ),
        );
    };

    const sendMail = () => {
        if (!composeForm.value.to || !composeForm.value.subject) {
            return;
        }

        router.post('/mail/compose', composeForm.value, {
            preserveScroll: true,
            onSuccess: () => {
                isComposing.value = false;
                composeForm.value = { to: '', subject: '', body: '' };
            },
        });
    };

    const hasMoreEmails = computed(() => pagination.value.hasMorePages && pagination.value.nextPage !== null);

    const loadMoreEmails = () => {
        if (!hasMoreEmails.value || isLoadingMoreEmails.value || pagination.value.nextPage === null) {
            return;
        }

        isLoadingMoreEmails.value = true;
        isAppendingEmails.value = true;

        router.get(
            '/mail',
            { page: pagination.value.nextPage },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['emails', 'pagination'],
                onFinish: () => {
                    isLoadingMoreEmails.value = false;
                    isAppendingEmails.value = false;
                },
            },
        );
    };

    return {
        currentFolder,
        currentEmails,
        activeFolders,
        isComposing,
        composeForm,
        flashSuccess,
        flashError,
        selectFolder,
        toggleStar,
        deleteEmail,
        archiveEmail,
        markAsRead,
        snoozeEmail,
        sendMail,
        hasMoreEmails,
        isLoadingMoreEmails,
        loadMoreEmails,
    };
}
