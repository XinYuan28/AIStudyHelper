/* Placeholder content so the Study Hub can be demoed without a database.
   When the database is connected, replace these with data loaded from the server. */

/* SAMPLE CONTENT */
const SAMPLE_NOTES = [{
        h: 'Key idea 1',
        p: 'A short summary of the first main concept from the uploaded material goes here.'
    },
    {
        h: 'Key idea 2',
        p: 'The second main concept, explained in plain language with the important terms.'
    },
    {
        h: 'Key idea 3',
        p: 'The third concept and how it connects to the ones before it.'
    },
    {
        h: 'Quick recap',
        p: 'A few lines that tie everything together so you can revise fast.'
    }
];

const SAMPLE_QUESTIONS = [{
        q: 'Sample question 1: which option is correct?',
        options: ['Option A', 'Option B', 'Option C', 'Option D'],
        answer: 1,
        topic: 'Key idea 1',
        explain: 'Explanation for question 1 taken from your material.'
    },
    {
        q: 'Sample question 2: which option is correct?',
        options: ['Option A', 'Option B', 'Option C', 'Option D'],
        answer: 0,
        topic: 'Key idea 2',
        explain: 'Explanation for question 2 taken from your material.'
    },
    {
        q: 'Sample question 3: which option is correct?',
        options: ['Option A', 'Option B', 'Option C', 'Option D'],
        answer: 2,
        topic: 'Key idea 3',
        explain: 'Explanation for question 3 taken from your material.'
    },
    {
        q: 'Sample question 4: which option is correct?',
        options: ['Option A', 'Option B', 'Option C', 'Option D'],
        answer: 3,
        topic: 'Key idea 1',
        explain: 'Explanation for question 4 taken from your material.'
    },
    {
        q: 'Sample question 5: which option is correct?',
        options: ['Option A', 'Option B', 'Option C', 'Option D'],
        answer: 1,
        topic: 'Key idea 2',
        explain: 'Explanation for question 5 taken from your material.'
    }
];

/* Text-answer questions: the student types the answer.
   `answer` is the main answer; `accept` is an optional list of other accepted wordings. */
const SAMPLE_TEXT_QUESTIONS = [{
        type: 'text',
        q: 'Sample text question 1: type the missing term.',
        answer: 'Sample answer 1',
        accept: ['answer 1'],
        topic: 'Key idea 1',
        explain: 'Explanation for text question 1 taken from your material.'
    },
    {
        type: 'text',
        q: 'Sample text question 2: type the missing term.',
        answer: 'Sample answer 2',
        accept: ['answer 2'],
        topic: 'Key idea 2',
        explain: 'Explanation for text question 2 taken from your material.'
    },
    {
        type: 'text',
        q: 'Sample text question 3: type the missing term.',
        answer: 'Sample answer 3',
        accept: ['answer 3'],
        topic: 'Key idea 3',
        explain: 'Explanation for text question 3 taken from your material.'
    },
    {
        type: 'text',
        q: 'Sample text question 4: type the missing term.',
        answer: 'Sample answer 4',
        accept: ['answer 4'],
        topic: 'Key idea 1',
        explain: 'Explanation for text question 4 taken from your material.'
    },
    {
        type: 'text',
        q: 'Sample text question 5: type the missing term.',
        answer: 'Sample answer 5',
        accept: ['answer 5'],
        topic: 'Key idea 2',
        explain: 'Explanation for text question 5 taken from your material.'
    }
];