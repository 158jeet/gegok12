<template>
    <div>
        <div v-if="this.success != null" class="alert alert-success" id="success-alert">
            {{ this.success }}
        </div>

        <div class="textarea-emoji-picker">

            <textarea
                ref="textarea"
                class="tw-form-control w-full"
                :value="value"
                @input="$emit('input', $event.target.value)"
                rows="1">
            </textarea>

            <!-- ✅ Vue 3 Emoji Picker -->
            <EmojiPicker
                v-show="showEmojiPicker"
                @select="addEmoji"
            />

            <span
                class="emoji-trigger"
                :class="{ 'triggered': showEmojiPicker }"
                @mousedown.prevent="toggleEmojiPicker"
            >
                <svg class="w-4 h-4 fill-current text-gray-600 mt-1"
                     viewBox="0 0 512 512">
                    <g>
                        <path d="M437.02,74.98C388.667,26.629,324.38,0,256,0S123.333,26.629,74.98,74.98C26.629,123.333,0,187.62,0,256
                        s26.629,132.668,74.98,181.02C123.333,485.371,187.62,512,256,512s132.667-26.629,181.02-74.98
                        C485.371,388.668,512,324.38,512,256S485.371,123.333,437.02,74.98z"/>
                    </g>
                </svg>
            </span>

        </div>
    </div>
</template>

<script>
import EmojiPicker from 'vue3-emoji-picker'
import 'vue3-emoji-picker/css'

export default {
    components: { EmojiPicker },

    props: {
        value: {
            type: String,
            default: ''
        }
    },

    data() {
        return {
            showEmojiPicker: false,
            success: null,
            errors: []
        }
    },

    methods: {
        toggleEmojiPicker() {
            this.showEmojiPicker = !this.showEmojiPicker
        },

        addEmoji(emoji) {
            const textarea = this.$refs.textarea
            const cursorPosition = textarea.selectionEnd
            const start = this.value.substring(0, textarea.selectionStart)
            const end = this.value.substring(textarea.selectionStart)

            // 🔥 IMPORTANT CHANGE HERE
            const emojiChar = emoji.i

            const text = start + emojiChar + end

            this.$emit('input', text)

            textarea.focus()

            this.$nextTick(() => {
                textarea.selectionEnd = cursorPosition + emojiChar.length
            })
        }
    }
}
</script>

<style scoped>
* {
    box-sizing: border-box;
}

.textarea-emoji-picker {
    position: relative;
    width: 395px;
    margin: 0 auto;
}

.textarea {
    width: 100%;
    outline: none;
    padding: 10px 28px 10px 10px;
    font-size: 15px;
    border: 1px solid #BABABA;
    color: #333;
    border-radius: 2px;
    resize: vertical;
}

.emoji-trigger {
    position: absolute;
    top: 6px;
    right: 10px;
    cursor: pointer;
    height: 20px;
}
</style>
