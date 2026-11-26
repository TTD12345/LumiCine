(() => {
    'use strict';

    const dateInput = document.getElementById('attendance-date');
    const rowsElement = document.getElementById('attendance-history');
    const emptyState = document.getElementById('attendance-history-empty');
    const toastRegion = document.getElementById('toast-region');
    let attendanceRecord = {
        check_in: '',
        check_out: '',
        late_minutes: 0,
        early_minutes: 0,
        has_assignment: false,
    };
    let historyRecords = [];
    let isLoading = false;
    let attendanceLoadError = false;
    let faceProfile = { enrolled: false };
    let faceStream = null;
    let faceModelsReady = false;
    let faceModelsLoading = null;
    let faceScanTimer = null;
    let faceScanBusy = false;
    let faceReadyFrames = 0;
    let faceAutoProcessing = false;
    let enrollmentSamples = [];
    let enrollmentPoseFrames = [];
    let enrollmentStep = 0;
    let enrollmentFirstSide = 0;

    const notify = (message, isError = false) => {
        const toast = document.createElement('div');
        toast.className = `employee-toast${isError ? ' error' : ''}`;
        toast.setAttribute('role', 'status');
        const icon = document.createElement('i');
        icon.className = `bi ${isError ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'}`;
        icon.setAttribute('aria-hidden', 'true');
        toast.append(icon, document.createTextNode(message));
        toastRegion.append(toast);
        window.setTimeout(() => toast.remove(), 4000);
    };

    const getCurrentTime = () => {
        const now = new Date();
        return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    };

    const formatMinutes = (minutes) => {
        if (minutes <= 0) return '0';
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return remainingMinutes ? `${hours} giờ ${remainingMinutes} phút` : `${hours}`;
    };

    const getWorkedMinutes = (record) => {
        if (Number.isFinite(Number(record.worked_minutes))) {
            return Number(record.worked_minutes);
        }
        if (!record.check_in || !record.check_out) return 0;
        const [inHour, inMinute] = record.check_in.split(':').map(Number);
        const [outHour, outMinute] = record.check_out.split(':').map(Number);
        return Math.max(0, outHour * 60 + outMinute - (inHour * 60 + inMinute));
    };

    const renderAttendance = () => {
        const { check_in: checkIn, check_out: checkOut } = attendanceRecord;
        const shiftName = document.getElementById('attendance-shift-name');
        const shiftTime = document.getElementById('attendance-shift-time');
        const shiftLocation = document.getElementById('attendance-shift-location');
        if (shiftName) shiftName.textContent = attendanceRecord.shift_name || 'Ca làm việc';
        if (shiftTime) {
            shiftTime.textContent = attendanceRecord.shift_start && attendanceRecord.shift_end
                ? `${attendanceRecord.shift_start} — ${attendanceRecord.shift_end}`
                : 'Chưa phân ca';
        }
        if (shiftLocation) {
            shiftLocation.textContent = attendanceRecord.location || 'Chưa có địa điểm';
        }
        const status = document.getElementById('attendance-status');
        const punchButton = document.getElementById('attendance-punch');
        const checkInState = document.getElementById('attendance-check-in-state');
        const checkOutState = document.getElementById('attendance-check-out-state');
        const isWorking = Boolean(checkIn) && !checkOut;
        const isComplete = Boolean(checkOut);
        const now = new Date();
        const currentDate = [
            now.getFullYear(),
            String(now.getMonth() + 1).padStart(2, '0'),
            String(now.getDate()).padStart(2, '0'),
        ].join('-');
        const isCurrentDate = dateInput.value === currentDate;
        const nextType = isWorking ? 'check_out' : 'check_in';
        const isAbsent = attendanceRecord.attendance_status === 'VANG_MAT';
        const shiftEnd = attendanceRecord.shift_end
            ? new Date(`${dateInput.value}T${attendanceRecord.shift_end}:00`)
            : null;
        if (shiftEnd && attendanceRecord.shift_start && attendanceRecord.shift_end <= attendanceRecord.shift_start) {
            shiftEnd.setDate(shiftEnd.getDate() + 1);
        }
        const shiftEndPassed = Boolean(shiftEnd) && now >= shiftEnd;
        const missingPunch = attendanceRecord.has_assignment
            && !checkIn
            && (dateInput.value < currentDate || shiftEndPassed);

        document.getElementById('attendance-check-in').textContent = checkIn || 'Chưa ghi nhận';
        document.getElementById('attendance-check-out').textContent = checkOut || 'Chưa ghi nhận';
        const lateMinutes = Number(attendanceRecord.late_minutes) || 0;
        const earlyMinutes = Number(attendanceRecord.early_minutes) || 0;
        checkInState.textContent = checkIn
            ? `Đã ghi nhận${lateMinutes > 0 ? ` · Muộn ${lateMinutes} phút` : ''}`
            : 'Chờ chấm công';
        checkOutState.textContent = checkOut
            ? `Đã ghi nhận${earlyMinutes > 0 ? ` · Về sớm ${earlyMinutes} phút` : ''}`
            : 'Chờ chấm công';
        checkInState.classList.toggle('is-recorded', Boolean(checkIn));
        checkOutState.classList.toggle('is-recorded', Boolean(checkOut));
        status.textContent = attendanceLoadError
            ? 'Lỗi tải dữ liệu'
            : !attendanceRecord.has_assignment ? 'Chưa phân ca'
            : isAbsent ? 'Nghỉ không phép'
                : missingPunch ? 'Thiếu chấm công'
                : lateMinutes > 0 ? 'Đi muộn'
                    : earlyMinutes > 0 ? 'Về sớm'
                        : isComplete ? 'Đã hoàn thành' : isWorking ? 'Đang làm việc' : 'Chưa bắt đầu';
        status.classList.toggle('is-working', isWorking);
        status.classList.toggle('is-complete', isComplete);
        punchButton.disabled = isLoading
            || !isCurrentDate
            || isComplete
            || isAbsent
            || (faceProfile.enrolled && !attendanceRecord.has_assignment);
        punchButton.querySelector('span').textContent = !faceProfile.enrolled
            ? 'Đăng ký khuôn mặt'
            : !attendanceRecord.has_assignment
                ? 'Chưa được phân ca'
                : nextType === 'check_out'
                    ? 'Chấm công ra bằng khuôn mặt'
                    : 'Chấm công vào bằng khuôn mặt';
        punchButton.querySelector('i').className = 'bi bi-camera';
        document.getElementById('attendance-status-summary').textContent = attendanceLoadError
            ? 'Không tải được'
            : !attendanceRecord.has_assignment ? 'Chưa được phân ca' : status.textContent;
    };

    const addCell = (row, value, className = '') => {
        const cell = document.createElement('td');
        if (className) cell.className = className;
        cell.textContent = value;
        row.append(cell);
    };

    const renderHistory = () => {
        rowsElement.replaceChildren();
        const recentRecords = historyRecords.slice(0, 7);
        recentRecords.forEach((record) => {
            const row = document.createElement('tr');
            const date = new Date(`${record.date}T00:00:00`);
            addCell(row, new Intl.DateTimeFormat('vi-VN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(date), 'attendance-history-date');
            addCell(row, record.check_in || '—', 'attendance-history-time');
            addCell(row, record.check_out || '—', 'attendance-history-time');
            addCell(row, record.check_out ? formatMinutes(getWorkedMinutes(record)) : record.check_in ? 'Đang làm việc' : '—');
            addCell(row, Number(record.late_minutes) > 0 ? `${Number(record.late_minutes)} phút` : '—');
            addCell(row, Number(record.early_minutes) > 0 ? `${Number(record.early_minutes)} phút` : '—');
            const statusCell = row.insertCell();
            const status = document.createElement('span');
            status.className = `attendance-history-status${record.check_out ? ' complete' : record.check_in ? ' working' : ''}`;
            status.textContent = record.attendance_status === 'VANG_MAT'
                ? 'Nghỉ không phép'
                : Number(record.late_minutes) > 0 ? 'Đi muộn'
                    : Number(record.early_minutes) > 0 ? 'Về sớm'
                        : record.check_out ? 'Hoàn thành' : record.check_in ? 'Đang làm' : 'Chưa chấm công';
            statusCell.append(status);
            rowsElement.append(row);
        });

        const hasRecords = recentRecords.length > 0;
        emptyState.hidden = hasRecords;
        rowsElement.closest('table').hidden = !hasRecords;
        document.getElementById('attendance-history-count').textContent = hasRecords
            ? `Hiển thị ${recentRecords.length} trong ${historyRecords.length} ngày đã ghi nhận`
            : 'Chưa có dữ liệu chấm công';

        const daysWithCheckIn = historyRecords.filter((record) => record.check_in).length;
        const totalMinutes = historyRecords.reduce((sum, record) => sum + getWorkedMinutes(record), 0);
        document.getElementById('attendance-days-count').textContent = String(daysWithCheckIn);
        document.getElementById('attendance-hours-total').textContent = formatMinutes(totalMinutes);
    };

    const loadAttendance = async () => {
        isLoading = true;
        renderAttendance();
        try {
            const result = await window.lumiCineApi('attendance', {
                query: { date: dateInput.value },
            });
            attendanceLoadError = false;
            attendanceRecord = result.record;
            historyRecords = result.history;
            faceProfile = result.face || { enrolled: false };
            renderAttendance();
            renderHistory();
        } catch (error) {
            attendanceLoadError = true;
            notify(error instanceof Error ? error.message : 'Không thể tải dữ liệu chấm công.', true);
            attendanceRecord = {
                check_in: '',
                check_out: '',
                late_minutes: 0,
                early_minutes: 0,
                has_assignment: false,
            };
            historyRecords = [];
            renderAttendance();
            renderHistory();
        } finally {
            isLoading = false;
            renderAttendance();
        }
    };

    const updateClock = () => {
        const now = new Date();
        document.getElementById('attendance-live-clock').textContent =
            `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
        document.getElementById('attendance-live-date').textContent =
            new Intl.DateTimeFormat('vi-VN', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }).format(now);
    };

    const faceDialog = document.getElementById('face-attendance-dialog');
    const faceVideo = document.getElementById('face-video');
    const faceCameraStatus = document.getElementById('face-camera-status');
    const faceCameraWrap = document.getElementById('face-camera-wrap');
    const faceOverlay = document.getElementById('face-overlay');
    const faceOverlayContext = faceOverlay.getContext('2d');
    let faceDialogMode = faceProfile.enrolled ? 'verify' : 'enroll';

    const loadFaceModels = async () => {
        if (faceModelsReady) return;
        if (!faceModelsLoading) {
            faceModelsLoading = (async () => {
                try {
                    if (!window.faceapi) throw new Error('Không thể tải thư viện nhận diện khuôn mặt.');
                    const modelUrl = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
                    await window.faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl);
                    await window.faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl);
                    await window.faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl);
                    faceModelsReady = true;
                } catch (error) {
                    faceModelsLoading = null;
                    throw error;
                }
            })();
        }
        await faceModelsLoading;
    };

    const setFaceFeedback = (state, message) => {
        faceCameraWrap.classList.remove('is-ready', 'is-too-far', 'is-too-close', 'is-no-face', 'is-searching');
        if (state) faceCameraWrap.classList.add(`is-${state}`);
        faceCameraStatus.textContent = message;
    };

    const clearFaceOverlay = () => {
        faceOverlayContext.clearRect(0, 0, faceOverlay.width, faceOverlay.height);
    };

    const drawFaceOverlay = (result, isReady) => {
        const { width, height } = faceVideo;
        if (!width || !height) return;
        if (faceOverlay.width !== width || faceOverlay.height !== height) {
            faceOverlay.width = width;
            faceOverlay.height = height;
        }
        clearFaceOverlay();
        const { x, y, width: faceWidth, height: faceHeight } = result.detection.box;
        faceOverlayContext.strokeStyle = isReady ? '#45df91' : '#ff626b';
        faceOverlayContext.lineWidth = Math.max(3, width / 180);
        faceOverlayContext.strokeRect(x, y, faceWidth, faceHeight);
    };

    const getFacePosition = (box) => {
        const videoWidth = faceVideo.videoWidth;
        const videoHeight = faceVideo.videoHeight;
        const containerWidth = faceCameraWrap.clientWidth;
        const containerHeight = faceCameraWrap.clientHeight;
        if (!videoWidth || !videoHeight || !containerWidth || !containerHeight) return null;

        const scale = Math.max(containerWidth / videoWidth, containerHeight / videoHeight);
        const cropX = (videoWidth * scale - containerWidth) / 2;
        const cropY = (videoHeight * scale - containerHeight) / 2;

        return {
            faceWidthRatio: box.width * scale / containerWidth,
            centerX: (box.x + box.width / 2) * scale - cropX,
            centerY: (box.y + box.height / 2) * scale - cropY,
            containerWidth,
            containerHeight,
        };
    };

    const estimateFaceYaw = (result) => {
        const nose = result.landmarks.getNose();
        const eyes = [
            ...result.landmarks.getLeftEye(),
            ...result.landmarks.getRightEye(),
        ];
        const noseCenter = nose.reduce((sum, point) => sum + point.x, 0) / nose.length;
        const eyeCenter = eyes.reduce((sum, point) => sum + point.x, 0) / eyes.length;
        return (noseCenter - eyeCenter) / result.detection.box.width;
    };

    const averageDescriptors = (descriptors) => descriptors[0].map((_, index) =>
        descriptors.reduce((sum, descriptor) => sum + descriptor[index], 0) / descriptors.length
    );

    const enrollmentPrompt = () => {
        if (enrollmentStep === 0) return 'Nhìn thẳng vào camera.';
        if (enrollmentStep === 1) return 'Tốt rồi — xoay nhẹ mặt sang một bên.';
        return 'Tốt rồi — xoay nhẹ mặt sang bên còn lại.';
    };

    const stopFaceScan = () => {
        if (faceScanTimer !== null) {
            window.clearTimeout(faceScanTimer);
            faceScanTimer = null;
        }
        faceReadyFrames = 0;
        faceScanBusy = false;
        faceAutoProcessing = false;
        enrollmentSamples = [];
        enrollmentPoseFrames = [];
        enrollmentStep = 0;
        enrollmentFirstSide = 0;
    };

    const stopFaceCamera = () => {
        if (faceStream) {
            faceStream.getTracks().forEach((track) => track.stop());
            faceStream = null;
        }
        faceVideo.srcObject = null;
    };

    const closeFaceDialog = () => {
        stopFaceScan();
        stopFaceCamera();
        if (faceDialog.open) faceDialog.close();
    };

    const detectFace = async () => {
        await loadFaceModels();
        const result = await window.faceapi
            .detectSingleFace(faceVideo, new window.faceapi.TinyFaceDetectorOptions({
                inputSize: 416,
                scoreThreshold: 0.55,
            }))
            .withFaceLandmarks()
            .withFaceDescriptor();
        return result;
    };

    const processFaceDescriptor = async (descriptor) => {
        if (faceDialogMode === 'enroll') {
            setFaceFeedback('searching', 'Đã quét đủ 3 góc — đang lưu khuôn mặt...');
            try {
                await window.lumiCineApi('attendance', {
                    method: 'POST',
                    body: { date: dateInput.value, operation: 'enroll', descriptors: descriptor },
                });
                faceProfile = { enrolled: true };
                stopFaceScan();
                closeFaceDialog();
                renderAttendance();
                notify('Đã đăng ký khuôn mặt từ 3 góc. Bấm chấm công để xác thực.');
            } catch (error) {
                faceAutoProcessing = false;
                enrollmentSamples = [];
                enrollmentPoseFrames = [];
                enrollmentStep = 0;
                enrollmentFirstSide = 0;
                setFaceFeedback('no-face', error instanceof Error ? error.message : 'Không thể đăng ký khuôn mặt.');
                notify(error instanceof Error ? error.message : 'Không thể đăng ký khuôn mặt.', true);
            }
            return;
        }

        isLoading = true;
        renderAttendance();
        setFaceFeedback('searching', 'Đã nhận diện. Đang ghi nhận chấm công...');
        try {
            const punchResult = await window.lumiCineApi('attendance', {
                method: 'POST',
                body: { date: dateInput.value, operation: 'punch', descriptor },
            });
            attendanceRecord = punchResult.record;
            closeFaceDialog();
            notify(punchResult.type === 'check_out'
                ? `Đã ghi nhận chấm công ra lúc ${attendanceRecord.check_out}.`
                : `Đã ghi nhận chấm công vào lúc ${attendanceRecord.check_in}.`);
            try {
                const refreshed = await window.lumiCineApi('attendance', {
                    query: { date: dateInput.value },
                });
                historyRecords = refreshed.history;
                faceProfile = refreshed.face || faceProfile;
                renderHistory();
            } catch (error) {
                notify('Chấm công đã được ghi nhận, nhưng không thể tải lại lịch sử.', true);
            }
        } catch (error) {
            faceReadyFrames = 0;
            setFaceFeedback('no-face', 'Chấm công chưa thành công. Đóng cửa sổ và mở lại để thử lại.');
            notify(error instanceof Error ? error.message : 'Không thể ghi nhận chấm công.', true);
        } finally {
            isLoading = false;
            renderAttendance();
        }
    };

    const scanFaceContinuously = async () => {
        if (!faceDialog.open || !faceStream || faceScanBusy || faceAutoProcessing) return;
        faceScanBusy = true;
        try {
            const result = await detectFace();
            if (!result) {
                faceReadyFrames = 0;
                enrollmentPoseFrames = [];
                clearFaceOverlay();
                setFaceFeedback('no-face', 'Không thấy khuôn mặt. Hãy nhìn vào camera.');
            } else {
                const box = result.detection.box;
                const position = getFacePosition(box);
                if (!position) {
                    faceReadyFrames = 0;
                    enrollmentPoseFrames = [];
                    setFaceFeedback('searching', 'Đang căn chỉnh hình camera...');
                } else if (position.faceWidthRatio < 0.24) {
                    faceReadyFrames = 0;
                    enrollmentPoseFrames = [];
                    drawFaceOverlay(result, false);
                    setFaceFeedback('too-far', 'Bạn đang ở xa — hãy tiến gần camera hơn.');
                } else if (position.faceWidthRatio > 0.52) {
                    faceReadyFrames = 0;
                    enrollmentPoseFrames = [];
                    drawFaceOverlay(result, false);
                    setFaceFeedback('too-close', 'Bạn đang quá gần — hãy lùi ra một chút.');
                } else if (
                    Math.abs(position.centerX / position.containerWidth - 0.5) > 0.18
                    || Math.abs(position.centerY / position.containerHeight - 0.5) > 0.22
                ) {
                    faceReadyFrames = 0;
                    enrollmentPoseFrames = [];
                    drawFaceOverlay(result, false);
                    setFaceFeedback('no-face', 'Hãy đưa khuôn mặt vào giữa khung.');
                } else {
                    if (faceDialogMode === 'enroll') {
                        const yaw = estimateFaceYaw(result);
                        const isFrontal = Math.abs(yaw) <= 0.08;
                        const isTurned = Math.abs(yaw) >= 0.12;
                        const isExpectedPose = enrollmentStep === 0
                            ? isFrontal
                            : enrollmentStep === 1
                                ? isTurned
                                : isTurned && Math.sign(yaw) !== enrollmentFirstSide;
                        drawFaceOverlay(result, true);

                        if (!isExpectedPose) {
                            enrollmentPoseFrames = [];
                            const prompt = enrollmentStep === 0
                                ? 'Nhìn thẳng chính diện để đăng ký góc đầu tiên.'
                                : enrollmentStep === 1
                                    ? 'Xoay nhẹ mặt sang một bên để đăng ký góc tiếp theo.'
                                    : 'Xoay nhẹ mặt sang bên còn lại để hoàn tất.';
                            setFaceFeedback('ready', `Khoảng cách phù hợp. ${prompt}`);
                        } else {
                            enrollmentPoseFrames.push(Array.from(result.descriptor));
                            const framesRequired = 3;
                            if (enrollmentPoseFrames.length >= framesRequired) {
                                enrollmentSamples.push(averageDescriptors(enrollmentPoseFrames));
                                if (enrollmentStep === 1) enrollmentFirstSide = Math.sign(yaw);
                                enrollmentStep += 1;
                                enrollmentPoseFrames = [];

                                if (enrollmentStep >= 3) {
                                    faceAutoProcessing = true;
                                    await processFaceDescriptor(enrollmentSamples);
                                } else {
                                    setFaceFeedback('ready', `${enrollmentPrompt()} (${enrollmentStep + 1}/3)`);
                                }
                            } else {
                                setFaceFeedback('ready', `Giữ yên — đang lấy góc ${enrollmentStep + 1}/3.`);
                            }
                        }
                    } else {
                        faceReadyFrames += 1;
                        drawFaceOverlay(result, true);
                        setFaceFeedback('ready', faceReadyFrames >= 3
                            ? 'Khoảng cách phù hợp — đang xác thực...'
                            : 'Khoảng cách phù hợp — giữ yên một chút.');
                        if (faceReadyFrames >= 3) {
                            faceAutoProcessing = true;
                            await processFaceDescriptor(Array.from(result.descriptor));
                        }
                    }
                }
            }
        } catch (error) {
            faceReadyFrames = 0;
            setFaceFeedback('no-face', error instanceof Error ? error.message : 'Không thể quét khuôn mặt.');
        } finally {
            faceScanBusy = false;
            if (faceDialog.open && faceStream && !faceAutoProcessing) {
                faceScanTimer = window.setTimeout(scanFaceContinuously, 350);
            }
        }
    };

    const openFaceDialog = async () => {
        if (isLoading) return;
        faceDialogMode = faceProfile.enrolled ? 'verify' : 'enroll';
        if (faceDialogMode === 'verify' && (!attendanceRecord.has_assignment || dateInput.value !== [
            new Date().getFullYear(),
            String(new Date().getMonth() + 1).padStart(2, '0'),
            String(new Date().getDate()).padStart(2, '0'),
        ].join('-'))) {
            notify('Chỉ có thể xác thực chấm công trong ngày hiện tại và khi đã được phân ca.', true);
            return;
        }

        if (!window.isSecureContext) {
            faceDialogMode = faceProfile.enrolled ? 'verify' : 'enroll';
            document.getElementById('face-dialog-kicker').textContent = 'KHÔNG THỂ MỞ CAMERA';
            document.getElementById('face-dialog-title').textContent = 'Cần truy cập an toàn';
            document.getElementById('face-dialog-description').textContent =
                'Camera chỉ hoạt động trên localhost hoặc HTTPS.';
            setFaceFeedback('no-face',
                'Hãy mở http://localhost hoặc https://ten-mien-cua-ban thay vì địa chỉ IP/Laragon.');
            faceDialog.showModal();
            notify('Trình duyệt yêu cầu truy cập qua localhost hoặc HTTPS để dùng camera.', true);
            return;
        }
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            setFaceFeedback('no-face',
                'Trình duyệt không hỗ trợ camera. Hãy dùng Chrome, Edge hoặc Firefox bản mới.');
            faceDialog.showModal();
            notify('Trình duyệt không hỗ trợ camera.', true);
            return;
        }

        document.getElementById('face-dialog-kicker').textContent =
            faceDialogMode === 'enroll' ? 'ĐĂNG KÝ KHUÔN MẶT' : 'XÁC THỰC KHUÔN MẶT';
        document.getElementById('face-dialog-title').textContent =
            faceDialogMode === 'enroll' ? 'Đăng ký khuôn mặt lần đầu' : 'Quét khuôn mặt';
        document.getElementById('face-dialog-description').textContent =
            faceDialogMode === 'enroll'
                ? 'Nhìn thẳng rồi lần lượt xoay mặt sang hai bên. Camera sẽ tự lưu 3 góc khi khung chuyển xanh.'
                : 'Camera tự quét và chấm công khi nhận diện ổn định. Đèn xanh là khoảng cách phù hợp.';
        faceReadyFrames = 0;
        enrollmentSamples = [];
        enrollmentPoseFrames = [];
        enrollmentStep = 0;
        enrollmentFirstSide = 0;
        clearFaceOverlay();
        setFaceFeedback('searching', 'Đang yêu cầu quyền truy cập camera...');
        faceDialog.showModal();

        try {
            faceStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
            faceVideo.srcObject = faceStream;
            await faceVideo.play();
            setFaceFeedback('searching', 'Đang tải mô hình nhận diện...');
            await loadFaceModels();
            setFaceFeedback('searching', faceDialogMode === 'enroll'
                ? 'Đang quét góc chính diện (1/3)...'
                : 'Đang quét. Hãy nhìn thẳng vào camera.');
            scanFaceContinuously();
        } catch (error) {
            let message = error instanceof Error ? error.message : 'Không thể khởi tạo camera.';
            if (error && error.name === 'NotAllowedError') {
                message = 'Bạn đã từ chối quyền camera. Hãy cho phép camera trong biểu tượng ổ khóa của trình duyệt.';
            } else if (error && error.name === 'NotFoundError') {
                message = 'Không tìm thấy webcam. Hãy kết nối camera và thử lại.';
            } else if (error && error.name === 'NotReadableError') {
                message = 'Webcam đang được ứng dụng khác sử dụng. Hãy đóng ứng dụng khác và thử lại.';
            } else if (error && error.name === 'SecurityError') {
                message = 'Trình duyệt chặn camera. Hãy dùng localhost hoặc HTTPS.';
            } else if (error && error.name === 'OverconstrainedError') {
                message = 'Camera không hỗ trợ cấu hình yêu cầu. Hãy thử lại.';
            }
            setFaceFeedback('no-face', message);
            notify(message, true);
        }
    };

    dateInput.addEventListener('change', loadAttendance);
    document.getElementById('attendance-punch').addEventListener('click', openFaceDialog);
    document.getElementById('close-face-dialog').addEventListener('click', closeFaceDialog);
    document.getElementById('cancel-face-dialog').addEventListener('click', closeFaceDialog);
    faceDialog.addEventListener('close', () => {
        stopFaceScan();
        stopFaceCamera();
    });
    document.getElementById('notifications-button').addEventListener('click', () => {
        window.location.href = '?action=notifications';
    });
    document.addEventListener('click', (event) => {
        const menu = document.getElementById('account-menu');
        const button = document.getElementById('account-menu-button');
        if (!event.target.closest('.topbar-account')) {
            menu.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        }
    });
    document.getElementById('account-menu-button').addEventListener('click', () => {
        const menu = document.getElementById('account-menu');
        menu.hidden = !menu.hidden;
        document.getElementById('account-menu-button').setAttribute('aria-expanded', String(!menu.hidden));
    });

    const sidebar = document.getElementById('employee-sidebar');
    const scrim = document.getElementById('sidebar-scrim');
    const toggleSidebar = () => {
        sidebar.classList.toggle('is-open');
        scrim.classList.toggle('is-visible');
    };
    document.getElementById('mobile-menu-button').addEventListener('click', toggleSidebar);
    scrim.addEventListener('click', toggleSidebar);
    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 740 && sidebar.classList.contains('is-open')) toggleSidebar();
        });
    });

    updateClock();
    window.setInterval(updateClock, 1000);
    loadAttendance();
})();
