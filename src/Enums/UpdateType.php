<?php

namespace ZenithGram\ZenithGram\Enums;

enum UpdateType: string
{
    /** Новое входящее сообщение любого типа — текст, фото, стикер и т.д. */
    case Message = 'message';

    /** Новая версия сообщения, которое известно боту и было отредактировано */
    case EditedMessage = 'edited_message';

    /** Новый входящий пост в канале любого типа — текст, фото, стикер и т.д. */
    case ChannelPost = 'channel_post';

    /** Новая версия поста в канале, который известен боту и был отредактирован */
    case EditedChannelPost = 'edited_channel_post';

    /** Новый входящий inline-запрос */
    case InlineQuery = 'inline_query';

    /** Результат inline-запроса, который был выбран пользователем и отправлен собеседнику */
    case ChosenInlineResult = 'chosen_inline_result';

    /** Новый входящий callback-запрос */
    case CallbackQuery = 'callback_query';

    /** Новый входящий запрос на доставку. Только для счетов с гибкой ценой */
    case ShippingQuery = 'shipping_query';

    /** Новый входящий запрос на предварительную проверку (pre-checkout). Содержит полную информацию о заказе */
    case PreCheckoutQuery = 'pre_checkout_query';

    /** Новое состояние опроса. Боты получают обновления только об остановленных опросах и опросах, отправленных самим ботом */
    case Poll = 'poll';

    /** Пользователь изменил свой ответ в неанонимном опросе. Боты получают новые голоса только в опросах, отправленных самим ботом */
    case PollAnswer = 'poll_answer';

    /** Статус участия бота в чате был обновлен. Для личных чатов это обновление приходит только при блокировке/разблокировке бота пользователем */
    case MyChatMember = 'my_chat_member';

    /** Статус участия участника чата был обновлен. Бот должен быть администратором в чате и явно указать "chat_member" в списке allowed_updates */
    case ChatMember = 'chat_member';

    /** Отправлен запрос на вступление в чат. Бот должен иметь права администратора can_invite_users */
    case ChatJoinRequest = 'chat_join_request';

    /** В чат добавлен буст (Chat Boost) */
    case ChatBoost = 'chat_boost';

    /** Из чата удален буст */
    case RemovedChatBoost = 'removed_chat_boost';
}