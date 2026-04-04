window.serviceCheckTimer = function serviceCheckTimer(nextCheckIso) {
    return {
        nextCheckIso,
        remainingLabel: nextCheckIso ? 'Calculating next check...' : 'Due now',
        timer: null,
        init() {
            this.updateLabel();
            this.timer = window.setInterval(() => this.updateLabel(), 1000);
        },
        destroy() {
            if (this.timer !== null) {
                window.clearInterval(this.timer);
                this.timer = null;
            }
        },
        updateLabel() {
            if (!this.nextCheckIso) {
                this.remainingLabel = 'Due now';

                return;
            }

            const diffInSeconds = Math.floor((new Date(this.nextCheckIso).getTime() - Date.now()) / 1000);

            if (!Number.isFinite(diffInSeconds) || diffInSeconds <= 0) {
                this.remainingLabel = 'Checking...';

                return;
            }

            const hours = Math.floor(diffInSeconds / 3600);
            const minutes = Math.floor((diffInSeconds % 3600) / 60);
            const seconds = diffInSeconds % 60;
            const segments = [];

            if (hours > 0) {
                segments.push(hours + 'h');
            }

            if (minutes > 0 || hours > 0) {
                segments.push(minutes + 'm');
            }

            segments.push(seconds + 's');

            this.remainingLabel = 'Next check in ' + segments.join(' ');
        },
    };
};
