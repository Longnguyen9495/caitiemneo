@props(['proposal'])

{{-- Phiếu xác nhận cho một đề xuất của trợ lý. Trợ lý điền được tới đâu thì
     điền, người duyệt sửa lại và bổ sung phần còn thiếu ngay tại đây rồi mới
     bấm duyệt — không phải nhắn qua nhắn lại để đọc cho máy chép. Máy chủ vẫn
     validate và ghi audit log y như khi nhập từ form quản trị. --}}
@php
    $builder = app(App\Services\Ai\AiActionFormBuilder::class);
    $branchId = $builder->branchId($proposal);
    $branchName = $builder->branchName($proposal);
    $fields = $branchId === null ? [] : $builder->fields($proposal);

    // Nhiều đề xuất cùng nằm trên một trang nên id phải tách theo từng phiếu,
    // và ô nhập chỉ nhận lại giá trị vừa gõ của đúng phiếu bị trả về lỗi.
    $formId = 'ai-action-'.$proposal->id;
    $isRetry = (int) old('proposal_id') === (int) $proposal->id;
    $errorKey = fn (string $key): string => 'payload.'.$key;
@endphp

@if ($branchId === null)
    <p class="alert alert-warning py-2 px-3 mt-3 mb-0 small">
        Bạn đang xem nhiều chi nhánh cùng lúc nên chưa rõ phiếu này thuộc chi nhánh nào.
        Chọn một chi nhánh cụ thể ở thanh trên rồi mở lại đề xuất.
    </p>
@else
    <form method="POST" action="{{ route('admin.ai.actions.confirm', $proposal) }}" class="ai-action-form mt-3">
        @csrf
        <input type="hidden" name="proposal_id" value="{{ $proposal->id }}">
        <input type="hidden" name="payload[branch_id]" value="{{ $branchId }}">

        <p class="small text-body-secondary mb-2">
            Chi nhánh <strong>{{ $branchName ?? 'chưa xác định' }}</strong>. Kiểm tra và sửa lại nếu trợ lý nghe nhầm, rồi bấm duyệt.
        </p>

        <div class="row g-2">
            @foreach ($fields as $field)
                @php
                    $key = $field['key'];
                    $inputId = $formId.'-'.$key;
                    $name = 'payload['.$key.']';
                    $value = $isRetry ? old('payload.'.$key, $field['value']) : $field['value'];
                    $hasError = $errors->has($errorKey($key));
                    $describedBy = array_filter([
                        $hasError ? $inputId.'-error' : null,
                        $field['help'] ? $inputId.'-help' : null,
                    ]);
                    $invalid = $hasError ? 'is-invalid' : '';
                    $extra = collect($field['attributes'])
                        ->map(fn ($attributeValue, $attribute): string => $attribute.'="'.e($attributeValue).'"')
                        ->implode(' ');
                @endphp

                @if ($field['widget'] === 'hidden')
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @continue
                @endif

                <div class="{{ in_array($field['widget'], ['textarea', 'checkboxes', 'toggle'], true) ? 'col-12' : 'col-12 col-sm-6' }}">
                    @if ($field['widget'] === 'toggle')
                        <label class="form-check">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input class="form-check-input" type="checkbox" name="{{ $name }}" value="1"
                                   id="{{ $inputId }}" @checked((bool) $value)>
                            <span class="form-check-label">{{ $field['label'] }}</span>
                        </label>
                    @elseif ($field['widget'] === 'checkboxes')
                        <fieldset>
                            <legend class="form-label">{{ $field['label'] }}</legend>
                            @forelse ($field['options'] as $optionValue => $optionLabel)
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="{{ $name }}[]"
                                           value="{{ $optionValue }}"
                                           @checked(in_array((string) $optionValue, array_map('strval', (array) $value), true))>
                                    <span class="form-check-label">{{ $optionLabel }}</span>
                                </label>
                            @empty
                                <p class="text-body-secondary small mb-0">Chi nhánh này chưa có mục nào để chọn.</p>
                            @endforelse
                        </fieldset>
                    @else
                        <label class="form-label" for="{{ $inputId }}">
                            {{ $field['label'] }}
                            @if ($field['required'])<span class="text-danger" aria-hidden="true">*</span>@endif
                        </label>

                        @if ($field['widget'] === 'select')
                            <select class="form-select form-select-sm {{ $invalid }}" id="{{ $inputId }}" name="{{ $name }}"
                                    @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
                                    @required($field['required'])>
                                @unless ($field['required'])
                                    <option value="">Không chọn</option>
                                @endunless
                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected((string) $optionValue === (string) $value)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @elseif ($field['widget'] === 'textarea')
                            <textarea class="form-control form-control-sm {{ $invalid }}" id="{{ $inputId }}" name="{{ $name }}" rows="2"
                                      @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
                                      @required($field['required'])>{{ $value }}</textarea>
                        @else
                            <input class="form-control form-control-sm {{ in_array($field['widget'], ['number', 'money', 'tel'], true) ? 'neo-num' : '' }} {{ $invalid }}"
                                   id="{{ $inputId }}" name="{{ $name }}" value="{{ $value }}"
                                   type="{{ match ($field['widget']) {
                                       'datetime' => 'datetime-local',
                                       'date' => 'date',
                                       'number', 'money' => 'number',
                                       'tel' => 'tel',
                                       'email' => 'email',
                                       default => 'text',
                                   } }}"
                                   @if ($field['widget'] === 'money') min="0" step="1000" inputmode="numeric" @endif
                                   @if ($field['widget'] === 'tel') inputmode="tel" @endif
                                   {!! $extra !!}
                                   @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
                                   @required($field['required'])>
                        @endif
                    @endif

                    @if ($field['help'])
                        <div class="form-text" id="{{ $inputId }}-help">{{ $field['help'] }}</div>
                    @endif

                    @error($errorKey($key))
                        <div class="invalid-feedback d-block" id="{{ $inputId }}-error">{{ $message }}</div>
                    @enderror
                </div>
            @endforeach
        </div>

        <p class="small text-body-secondary mt-3 mb-2">
            Chưa có dữ liệu nào bị thay đổi. Bấm duyệt sẽ yêu cầu phiên mật khẩu gần đây và được ghi vào lịch sử thao tác.
        </p>

        @php($destructive = app(App\Services\Ai\AiActionCatalog::class)->definition($proposal->type)['destructive'] ?? false)
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm {{ $destructive ? 'btn-danger' : 'btn-primary' }}" type="submit">
                {{ $destructive ? 'Xác nhận hủy' : 'Duyệt và thực hiện' }}
            </button>
            {{-- Cùng một form: bỏ qua đề xuất thì không cần điền cho đủ ô. --}}
            <button class="btn btn-sm btn-outline-secondary" type="submit" formnovalidate
                    formaction="{{ route('admin.ai.actions.reject', $proposal) }}">
                Từ chối
            </button>
        </div>
    </form>
@endif
